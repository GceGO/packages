<?php

declare(strict_types=1);

namespace Gcego\Core\Extensions;

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Bootstrap\StartupTelemetry;
use Gcego\Core\Contracts\ExtensionInterface;
use Gcego\Core\Diagnostics\DiagnosticRedactor;
use Gcego\Core\Diagnostics\InfrastructureIssues;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Support\DependencyGraph;
use Throwable;

/**
 * Registers extensions and activates them once, in dependency order.
 *
 * An extension is skipped (not an error) when a package in its whenPackages
 * list is inactive, or when an extension it requires was skipped for that
 * reason. Missing or explicitly disabled requirements are errors.
 */
final class ExtensionManager
{
    /**
     * @var array<string, ExtensionInterface>
     */
    private array $extensions = [];

    /**
     * @var array<string, ExtensionDescriptor>
     */
    private array $descriptors = [];

    /**
     * @var array<string, true>
     */
    private array $active = [];

    /**
     * @var array<string, string>
     */
    private array $skipped = [];

    private bool $locked = false;

    private bool $initializing = false;

    public function __construct(private readonly StartupTelemetry $telemetry = new StartupTelemetry, private readonly InfrastructureIssues $issues = new InfrastructureIssues) {}

    /**
     * @throws ExtensionException
     */
    public function register(ExtensionInterface $extension): void
    {
        if (in_array($extension, $this->extensions, true)) {
            return;
        }

        if ($this->locked) {
            $this->issues->record('extensions.registration.late', DiagnosticRedactor::className($extension::class));
            throw ExtensionException::afterBoot('unregistered extension');
        }
        if ($this->initializing) {
            $this->issues->record('extensions.registration.mutation', DiagnosticRedactor::className($extension::class));
            throw ExtensionException::duringInitialization('unregistered extension');
        }

        try {
            $descriptor = $extension->descriptor();
        } catch (Throwable $exception) {
            $this->issues->record('extensions.descriptor.invalid', exception: $exception);
            throw $exception;
        }
        $id = $descriptor->id;

        if (isset($this->extensions[$id])) {
            if ($this->extensions[$id] === $extension) {
                return;
            }

            $this->issues->record('extensions.registration.duplicate', $id);
            throw ExtensionException::duplicate($id, $this->extensions[$id]::class, $extension::class);
        }

        $this->extensions[$id] = $extension;
        $this->descriptors[$id] = $descriptor;
    }

    public function has(string $id): bool
    {
        return isset($this->extensions[$id]);
    }

    /**
     * @throws ExtensionException
     */
    public function get(string $id): ExtensionInterface
    {
        return $this->extensions[$id] ?? throw ExtensionException::notRegistered($id);
    }

    /**
     * @throws ExtensionException
     */
    public function descriptor(string $id): ExtensionDescriptor
    {
        return $this->descriptors[$id] ?? throw ExtensionException::notRegistered($id);
    }

    /**
     * @return list<ExtensionDescriptor> In registration order.
     */
    public function descriptors(): array
    {
        return array_values($this->descriptors);
    }

    public function isActive(string $id): bool
    {
        return isset($this->active[$id]);
    }

    /**
     * @return list<string> In activation order.
     */
    public function active(): array
    {
        return array_keys($this->active);
    }

    /**
     * @return array<string, string> Extension id => reason it was skipped.
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * Compute which extensions activate, and in which order, without activating anything.
     *
     * @param  list<string>  $disabled
     * @param  list<string>  $packageCapabilities
     * @param  list<string>  $activePackages
     * @return array{order: list<string>, skipped: array<string, string>}
     *
     * @throws ExtensionException
     */
    public function plan(array $disabled, array $activePackages, array $packageCapabilities = []): array
    {
        $skipped = [];

        foreach ($this->descriptors as $id => $descriptor) {
            if (in_array($id, $disabled, true)) {
                $skipped[$id] = 'disabled in gcego.extensions.disabled';

                continue;
            }

            $inactive = array_values(array_diff($descriptor->whenPackages, $activePackages));

            if ($inactive !== []) {
                $skipped[$id] = sprintf('package %s is not active', implode(', ', $inactive));
            }
        }

        // Cascade: requiring an extension that was skipped for a package reason skips the dependent too.
        do {
            $changed = false;

            foreach ($this->descriptors as $id => $descriptor) {
                if (isset($skipped[$id])) {
                    continue;
                }

                foreach ($descriptor->requires as $requirement) {
                    if (isset($skipped[$requirement]) && ! in_array($requirement, $disabled, true)) {
                        $skipped[$id] = sprintf('required extension %s is inactive', $requirement);
                        $changed = true;

                        break;
                    }
                }
            }
        } while ($changed);

        // Validate only after eligibility has converged. Otherwise a dependent
        // can fail before a later registration causes it to be skipped.
        foreach ($this->descriptors as $id => $descriptor) {
            if (isset($skipped[$id])) {
                continue;
            }

            foreach ($descriptor->requires as $requirement) {
                if (! isset($this->descriptors[$requirement])) {
                    throw ExtensionException::missing($id, [$requirement]);
                }

                if (in_array($requirement, $disabled, true)) {
                    throw ExtensionException::requiresDisabled($id, $requirement);
                }
            }
        }

        $nodes = [];

        foreach ($this->descriptors as $id => $descriptor) {
            if (! isset($skipped[$id])) {
                $nodes[$id] = ['requires' => $descriptor->requires, 'optional' => $descriptor->optional];
            }
        }

        $graph = new DependencyGraph($nodes);
        $cycle = $graph->cycle();

        if ($cycle !== null) {
            throw ExtensionException::circular($cycle);
        }

        $available = $packageCapabilities;
        foreach ($graph->order() as $id) {
            if (array_diff($this->descriptors[$id]->requiresCapabilities, $available) !== []) {
                throw new ExtensionException(sprintf('GCEGO extension "%s" requires capabilities that are not available before its activation.', $id), diagnosticCode: 'extensions.dependencies.capabilities');
            }
            array_push($available, ...$this->descriptors[$id]->capabilities);
        }

        ksort($skipped);

        return ['order' => $graph->order(), 'skipped' => $skipped];
    }

    /**
     * Activate every eligible extension that is not active yet.
     *
     * @param  list<string>  $disabled
     *
     * @throws ExtensionException|BootstrapException
     */
    public function activate(BootstrapContext $context, array $disabled = []): void
    {
        if ($this->initializing) {
            $this->issues->record('extensions.recursive_boot');
            throw new BootstrapException('Cannot activate GCEGO extensions recursively.');
        }

        $this->initializing = true;

        try {
            $plan = $this->plan($disabled, $context->packages, $context->capabilities);
            $this->skipped = $plan['skipped'];

            foreach ($plan['order'] as $id) {
                if (isset($this->active[$id])) {
                    continue;
                }

                try {
                    $this->telemetry->measure('extension', $id, function () use ($id, $context): void {
                        $this->extensions[$id]->activate($context);
                        $this->active[$id] = true;
                    });
                } catch (Throwable $exception) {
                    throw BootstrapException::extensionFailed($id, $exception);
                }

            }
        } finally {
            $this->initializing = false;
        }
    }

    public function lock(): void
    {
        $this->locked = true;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }
}
