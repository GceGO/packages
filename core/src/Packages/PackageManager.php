<?php

declare(strict_types=1);

namespace Gcego\Core\Packages;

use Composer\InstalledVersions;
use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\Bootable;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Exceptions\PackageRegistrationException;
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

    public function __construct(
        private readonly PackageDependencyValidator $validator = new PackageDependencyValidator,
    ) {}

    /**
     * Register a package. Registering the same instance again is a no-op;
     * a different object claiming a registered id is rejected.
     *
     * @throws PackageRegistrationException
     */
    public function register(PackageInterface $package): void
    {
        $descriptor = $package->descriptor();
        $id = $descriptor->id;

        if (isset($this->packages[$id])) {
            if ($this->packages[$id] === $package) {
                return;
            }

            throw PackageRegistrationException::duplicate($id, $this->packages[$id]::class, $package::class);
        }

        if ($this->locked) {
            throw PackageRegistrationException::afterBoot($id);
        }

        $this->packages[$id] = $package;
        $this->descriptors[$id] = $descriptor->version === null
            ? $descriptor->withVersion(self::installedVersion($id))
            : $descriptor;
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
        foreach ($order as $id) {
            if (isset($this->booted[$id])) {
                continue;
            }

            $package = $this->get($id);

            if ($package instanceof Bootable) {
                try {
                    $package->bootPackage($context);
                } catch (Throwable $exception) {
                    throw BootstrapException::packageFailed($id, $exception);
                }
            }

            $this->booted[$id] = true;
        }
    }

    /**
     * Reject further registrations (called once the framework has booted).
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
