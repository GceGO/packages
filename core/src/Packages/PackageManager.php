<?php

declare(strict_types=1);

namespace Gcego\Core\Packages;

use Composer\InstalledVersions;
use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Bootstrap\StartupTelemetry;
use Gcego\Core\Contracts\Bootable;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Diagnostics\DiagnosticRedactor;
use Gcego\Core\Diagnostics\InfrastructureIssues;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Illuminate\Contracts\Support\DeferrableProvider;
use OutOfBoundsException;
use Throwable;

/**
 * Tracks the GCEGO packages taking part in this application and boots them in dependency order.
 *
 * Holds framework-wide metadata only (one container singleton per application).
 */
final class PackageManager
{
    /**
     * @var array<string, PackageInterface>
     */
    private array $packages = [];

    /**
     * @var array<string, PackageDescriptor>
     */
    private array $descriptors = [];

    /**
     * @var array<string, true>
     */
    private array $booted = [];

    private bool $locked = false;

    private bool $initializing = false;

    public function __construct(
        private readonly PackageDependencyValidator $validator = new PackageDependencyValidator,
        private readonly StartupTelemetry $telemetry = new StartupTelemetry,
        private readonly InfrastructureIssues $issues = new InfrastructureIssues,
    ) {}

    /**
     * Register a package. Registering the same instance again is a no-op;
     * a different object claiming a registered id is rejected.
     *
     * @throws PackageRegistrationException
     */
    public function register(PackageInterface $package): void
    {
        if (in_array($package, $this->packages, true)) {
            return;
        }

        if ($this->locked) {
            $this->issues->record('packages.registration.late', DiagnosticRedactor::className($package::class));
            throw PackageRegistrationException::afterBoot('unregistered package');
        }
        if ($this->initializing) {
            $this->issues->record('packages.registration.mutation', DiagnosticRedactor::className($package::class));
            throw PackageRegistrationException::duringInitialization('unregistered package');
        }

        try {
            $descriptor = $package->descriptor();
        } catch (Throwable $exception) {
            $this->issues->record('packages.descriptor.invalid', exception: $exception);
            throw $exception;
        }
        $id = $descriptor->id;
        if ($package instanceof DeferrableProvider) {
            $this->issues->record('packages.provider.deferred', $id);
            throw PackageRegistrationException::invalidMetadata($id, 'GCEGO package providers must not be deferred.');
        }

        if (isset($this->packages[$id])) {
            if ($this->packages[$id] === $package) {
                return;
            }

            $this->issues->record('packages.registration.duplicate', $id);
            throw PackageRegistrationException::duplicate($id, $this->packages[$id]::class, $package::class);
        }

        $this->packages[$id] = $package;
        $this->descriptors[$id] = $descriptor->version === null
            ? $descriptor->withVersion(self::installedVersion($id))
            : $descriptor;
    }

    /** Copy cached registration metadata without boot state, optionally preserving the registration lock. */
    public function snapshot(bool $preserveLock = false): self
    {
        $snapshot = new self($this->validator);
        $snapshot->packages = $this->packages;
        $snapshot->descriptors = $this->descriptors;
        $snapshot->locked = $preserveLock && $this->locked;

        return $snapshot;
    }

    public function has(string $id): bool
    {
        return isset($this->packages[$id]);
    }

    /**
     * @throws PackageRegistrationException
     */
    public function get(string $id): PackageInterface
    {
        return $this->packages[$id] ?? throw PackageRegistrationException::notRegistered($id);
    }

    /**
     * @throws PackageRegistrationException
     */
    public function descriptor(string $id): PackageDescriptor
    {
        return $this->descriptors[$id] ?? throw PackageRegistrationException::notRegistered($id);
    }

    /**
     * @return list<PackageDescriptor> In registration order.
     */
    public function descriptors(): array
    {
        return array_values($this->descriptors);
    }

    /**
     * @return list<string> Sorted dependency identifiers; missing requirements are included.
     */
    public function dependencies(string $id, bool $transitive = false, bool $includeOptional = false): array
    {
        $this->descriptor($id);
        $seen = [];
        $pending = [$id];

        while ($pending !== []) {
            $current = array_pop($pending);
            $descriptor = $this->descriptors[$current] ?? null;

            if ($descriptor === null) {
                continue;
            }

            foreach ([...$descriptor->requires, ...($includeOptional ? $descriptor->optional : [])] as $dependency) {
                if ($dependency === $id || isset($seen[$dependency])) {
                    continue;
                }

                $seen[$dependency] = true;

                if ($transitive) {
                    $pending[] = $dependency;
                }
            }
        }

        $dependencies = array_keys($seen);
        sort($dependencies);

        return $dependencies;
    }

    /** @return list<string> Sorted registered dependants. */
    public function dependants(string $id, bool $transitive = false, bool $includeOptional = false): array
    {
        $this->descriptor($id);
        $dependants = [];

        foreach (array_keys($this->descriptors) as $candidate) {
            if ($candidate !== $id && in_array($id, $this->dependencies($candidate, $transitive, $includeOptional), true)) {
                $dependants[] = $candidate;
            }
        }

        sort($dependants);

        return $dependants;
    }

    public function isBooted(string $id): bool
    {
        return isset($this->booted[$id]);
    }

    /**
     * @return list<string> In boot order.
     */
    public function booted(): array
    {
        return array_keys($this->booted);
    }

    /**
     * Validate dependencies and return the boot order of the enabled packages.
     *
     * @param  list<string>  $disabled
     * @return list<string>
     *
     * @throws PackageDependencyException
     */
    public function resolve(array $disabled = []): array
    {
        return $this->validator->resolve($this->descriptors(), $disabled);
    }

    /**
     * Every dependency problem of the registered packages, without throwing.
     *
     * @param  list<string>  $disabled
     * @return list<PackageDependencyException>
     */
    public function problems(array $disabled = []): array
    {
        return $this->validator->problems($this->descriptors(), $disabled);
    }

    /**
     * Boot the given packages in order, skipping those already booted.
     *
     * Packages booted before a failure stay booted, so a retry does not
     * initialize them twice.
     *
     * @param  list<string>  $order
     *
     * @throws BootstrapException
     */
    public function boot(array $order, BootstrapContext $context): void
    {
        if ($this->initializing) {
            $this->issues->record('packages.recursive_boot');
            throw new BootstrapException('Cannot boot GCEGO packages recursively.');
        }

        $this->initializing = true;

        try {
            foreach ($order as $id) {
                if (isset($this->booted[$id])) {
                    continue;
                }

                $package = $this->get($id);

                try {
                    $this->telemetry->measure('package', $id, function () use ($package, $context, $id): void {
                        if ($package instanceof Bootable) {
                            $package->bootPackage($context);
                        }

                        $this->booted[$id] = true;
                    });
                } catch (Throwable $exception) {
                    throw BootstrapException::packageFailed($id, $exception);
                }
            }
        } finally {
            $this->initializing = false;
        }
    }

    /**
     * Reject further registrations before Core resolves dependencies and runs boot hooks.
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    private static function installedVersion(string $id): ?string
    {
        try {
            return InstalledVersions::isInstalled($id) ? InstalledVersions::getPrettyVersion($id) : null;
        } catch (OutOfBoundsException) {
            return null;
        }
    }
}
