<?php

declare(strict_types=1);

namespace Gcego\Core\Support;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Stringable;

/**
 * The installed version of a Composer package, read from Composer's runtime metadata.
 *
 * Nothing is hardcoded: tagged installs report their tag ("1.2.0"), monorepo
 * and VCS installs report Composer's development version ("dev-main").
 * Composer keeps this metadata in memory, and the container shares one
 * instance, so the files are not re-read on every request.
 */
final readonly class FrameworkVersion implements Stringable
{
    public const string Unknown = 'dev';

    public function __construct(
        public string $package,
        public string $version,
        public ?string $reference = null,
    ) {}

    public static function detect(string $package = 'gcego/core'): self
    {
        try {
            if (InstalledVersions::isInstalled($package)) {
                return new self(
                    $package,
                    InstalledVersions::getPrettyVersion($package) ?? self::Unknown,
                    InstalledVersions::getReference($package),
                );
            }
        } catch (OutOfBoundsException) {
            // Not installed through Composer; fall through to the development marker.
        }

        return new self($package, self::Unknown);
    }

    /**
     * Whether this is an untagged development build ("dev-main", "1.x-dev", "dev", "...+no-version-set").
     */
    public function isDevelopment(): bool
    {
        return $this->version === self::Unknown
            || str_starts_with($this->version, 'dev-')
            || str_ends_with($this->version, '-dev')
            || str_contains($this->version, 'no-version-set');
    }

    public function toString(): string
    {
        return $this->version;
    }

    public function __toString(): string
    {
        return $this->version;
    }
}
