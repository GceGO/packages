<?php

declare(strict_types=1);

namespace Gcego\Core\Extensions;

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\ExtensionInterface;
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

    /**
     * @throws ExtensionException
     */
    public function register(ExtensionInterface $extension): void
    {
        $descriptor = $extension->descriptor();
        $id = $descriptor->id;

        if (isset($this->extensions[$id])) {
            if ($this->extensions[$id] === $extension) {
                return;
            }

            throw ExtensionException::duplicate($id, $this->extensions[$id]::class, $extension::class);
        }

        if ($this->locked) {
            throw ExtensionException::afterBoot($id);
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
     * @param  list<string>  $activePackages
     * @return array{order: list<string>, skipped: array<string, string>}
     *
     * @throws ExtensionException
     */
    public function plan(array $disabled, array $activePackages): array
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
                    if (! isset($this->descriptors[$requirement])) {
                        throw ExtensionException::missing($id, [$requirement]);
                    }

                    if (in_array($requirement, $disabled, true)) {
                        throw ExtensionException::requiresDisabled($id, $requirement);
                    }

                    if (isset($skipped[$requirement])) {
                        $skipped[$id] = sprintf('required extension %s is inactive', $requirement);
                        $changed = true;

                        break;
                    }
                }
            }
        } while ($changed);

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
        $plan = $this->plan($disabled, $context->packages);
        $this->skipped = $plan['skipped'];

        foreach ($plan['order'] as $id) {
            if (isset($this->active[$id])) {
                continue;
            }

            try {
                $this->extensions[$id]->activate($context);
            } catch (Throwable $exception) {
                throw BootstrapException::extensionFailed($id, $exception);
            }

            $this->active[$id] = true;
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
