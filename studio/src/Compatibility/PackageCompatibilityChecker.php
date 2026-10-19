<?php

declare(strict_types=1);

namespace Gcego\Studio\Compatibility;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Gcego\Core\Packages\PackageManager;
use Gcego\Studio\Integration\PackageIntegrationManager;
use Illuminate\Foundation\Application as Laravel;
use Throwable;

/**
 * Checks what is really installed against what is required:
 *
 * - PHP, Laravel and Livewire against Studio's minimums;
 * - every installed GCEGO package's own Composer constraints on PHP and
 *   on other GCEGO packages (read from its composer.json), evaluated with
 *   composer/semver when available (otherwise reported as unverified,
 *   never assumed compatible);
 * - gcego/core's package dependency problems (descriptors).
 */
final readonly class PackageCompatibilityChecker
{
    public const string MinimumPhp = '8.3.0';

    public const string MinimumLaravel = '13.0.0';

    public const string MinimumLivewire = '4.0.0';

    public function __construct(private PackageManager $packages) {}

    public function check(): CompatibilityReport
    {
        $laravel = Laravel::VERSION;
        $livewire = PackageIntegrationManager::version('livewire/livewire');
        $versions = ['php' => PHP_VERSION, 'laravel/framework' => $laravel, 'livewire/livewire' => $livewire];
        $problems = [];
        $notices = [];

        if (version_compare(PHP_VERSION, self::MinimumPhp, '<')) {
            $problems[] = sprintf('PHP %s is older than the required %s.', PHP_VERSION, self::MinimumPhp);
        }

        if (version_compare($laravel, self::MinimumLaravel, '<')) {
            $problems[] = sprintf('Laravel %s is older than the required %s.', $laravel, self::MinimumLaravel);
        }

        if ($livewire === null) {
            $problems[] = 'livewire/livewire is not installed (composer require livewire/livewire).';
        } elseif (preg_match('/^v?(\d+\.\d+(\.\d+)?)/', $livewire, $match) === 1 && version_compare($match[1], self::MinimumLivewire, '<')) {
            $problems[] = sprintf('Livewire %s is older than the required %s.', $livewire, self::MinimumLivewire);
        }

        foreach ([...array_keys(PackageIntegrationManager::Required), ...array_keys(PackageIntegrationManager::Optional)] as $package) {
            $version = PackageIntegrationManager::version($package);

            if ($version === null) {
                continue;
            }

            $versions[$package] = $version;

            foreach ($this->requirements($package) as $dependency => $constraint) {
                if ($dependency === 'php') {
                    $result = $this->satisfies(PHP_VERSION, $constraint);
                } elseif (str_starts_with($dependency, 'gcego/')) {
                    $installed = PackageIntegrationManager::version($dependency);

                    if ($installed === null) {
                        $problems[] = sprintf('%s requires %s %s, which is not installed.', $package, $dependency, $constraint);

                        continue;
                    }

                    $result = $this->satisfies($installed, $constraint);
                } else {
                    continue;
                }

                if ($result === false) {
                    $problems[] = sprintf('%s requires %s %s, but %s is installed.', $package, $dependency, $constraint, $dependency === 'php' ? PHP_VERSION : PackageIntegrationManager::version($dependency));
                } elseif ($result === null) {
                    $notices[] = sprintf('%s requires %s %s; composer/semver is not available to verify it.', $package, $dependency, $constraint);
                }
            }
        }

        foreach ($this->corePackageProblems() as $problem) {
            $problems[] = $problem;
        }

        return new CompatibilityReport($versions, array_values(array_unique($problems)), array_values(array_unique($notices)));
    }

    /**
     * @return array<string, string>
     */
    private function requirements(string $package): array
    {
        try {
            $path = InstalledVersions::getInstallPath($package);
            $file = $path === null ? null : realpath($path.'/composer.json');

            if ($file === false || $file === null) {
                return [];
            }

            $composer = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
            $require = is_array($composer) && is_array($composer['require'] ?? null) ? $composer['require'] : [];

            return array_filter($require, static fn (mixed $constraint, mixed $name): bool => is_string($constraint) && is_string($name), ARRAY_FILTER_USE_BOTH);
        } catch (Throwable) {
            return [];
        }
    }

    private function satisfies(string $version, string $constraint): ?bool
    {
        if (! class_exists(Semver::class)) {
            return null;
        }

        try {
            return Semver::satisfies(ltrim($version, 'v'), $constraint);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function corePackageProblems(): array
    {
        try {
            return array_map(static fn (mixed $problem): string => match (true) {
                is_string($problem) => $problem,
                $problem instanceof Throwable => $problem->getMessage(),
                is_object($problem) && method_exists($problem, '__toString') => (string) $problem,
                default => (string) json_encode($problem),
            }, array_values($this->packages->problems()));
        } catch (Throwable) {
            return [];
        }
    }
}
