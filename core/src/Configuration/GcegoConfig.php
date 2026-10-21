<?php

declare(strict_types=1);

namespace Gcego\Core\Configuration;

use Gcego\Core\Exceptions\ConfigurationException;

/**
 * A typed, validated, immutable view of config('gcego').
 *
 * Laravel's configuration repository stays the single source of truth; this
 * object interprets it once. The container builds it on first use (normally
 * during framework boot, after every provider has registered), so runtime
 * code never calls env().
 */
final readonly class GcegoConfig
{
    /**
     * @param  list<string>  $disabledPackages
     * @param  list<string>  $disabledExtensions
     */
    private function __construct(
        private bool $enabled,
        private string $name,
        private bool $autoBoot,
        private bool $environmentChecks,
        private array $disabledPackages,
        private array $disabledExtensions,
        private bool $profiling,
        private float $slowBootMs,
        private float $slowPackageMs,
        private float $slowExtensionMs,
    ) {}

    /**
     * @param  array<mixed>  $config  The raw config('gcego') array.
     *
     * @throws ConfigurationException listing every invalid key.
     */
    public static function fromArray(array $config, ConfigurationValidator $validator = new ConfigurationValidator): self
    {
        $result = $validator->validate($config);

        if ($result['errors'] !== []) {
            throw ConfigurationException::invalid($result['errors']);
        }

        $values = $result['values'];

        return new self(
            $values['enabled'],
            $values['name'],
            $values['auto_boot'],
            $values['environment_checks'],
            $values['disabled_packages'],
            $values['disabled_extensions'],
            $values['profiling'],
            $values['slow_boot_ms'],
            $values['slow_package_ms'],
            $values['slow_extension_ms'],
        );
    }

    public static function defaults(): self
    {
        return self::fromArray([]);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function autoBoot(): bool
    {
        return $this->autoBoot;
    }

    public function environmentChecks(): bool
    {
        return $this->environmentChecks;
    }

    /**
     * @return list<string>
     */
    public function disabledPackages(): array
    {
        return $this->disabledPackages;
    }

    public function isPackageDisabled(string $id): bool
    {
        return in_array($id, $this->disabledPackages, true);
    }

    /**
     * @return list<string>
     */
    public function disabledExtensions(): array
    {
        return $this->disabledExtensions;
    }

    public function isExtensionDisabled(string $id): bool
    {
        return in_array($id, $this->disabledExtensions, true);
    }

    public function profiling(): bool
    {
        return $this->profiling;
    }

    public function slowBootMs(): float
    {
        return $this->slowBootMs;
    }

    public function slowPackageMs(): float
    {
        return $this->slowPackageMs;
    }

    public function slowExtensionMs(): float
    {
        return $this->slowExtensionMs;
    }

    /**
     * @return array{enabled: bool, name: string, auto_boot: bool, environment_checks: bool, packages: array{disabled: list<string>}, extensions: array{disabled: list<string>}, diagnostics: array{profiling: bool, slow_boot_ms: float, slow_package_ms: float, slow_extension_ms: float}}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'name' => $this->name,
            'auto_boot' => $this->autoBoot,
            'environment_checks' => $this->environmentChecks,
            'packages' => ['disabled' => $this->disabledPackages],
            'extensions' => ['disabled' => $this->disabledExtensions],
            'diagnostics' => ['profiling' => $this->profiling, 'slow_boot_ms' => $this->slowBootMs, 'slow_package_ms' => $this->slowPackageMs, 'slow_extension_ms' => $this->slowExtensionMs],
        ];
    }
}
