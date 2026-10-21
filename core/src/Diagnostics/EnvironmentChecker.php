<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use Illuminate\Contracts\Foundation\Application;

/**
 * Cheap, side-effect-free checks of the PHP and Laravel runtime.
 *
 * No network, filesystem or database access: safe to run during boot.
 */
final readonly class EnvironmentChecker
{
    public const string MinimumPhpVersion = '8.3.0';

    public const string MinimumLaravelVersion = '13.0.0';

    /**
     * Extensions Laravel itself requires.
     */
    public const array RequiredExtensions = ['ctype', 'filter', 'hash', 'mbstring', 'openssl', 'session', 'tokenizer'];

    /**
     * @param  string  $phpVersion  Overridable for tests.
     * @param  list<string>|null  $loadedExtensions  Overridable for tests; null reads the running PHP.
     */
    public function __construct(
        private Application $app,
        private string $phpVersion = PHP_VERSION,
        private ?array $loadedExtensions = null,
    ) {}

    /**
     * @return list<DiagnosticResult>
     */
    public function check(): array
    {
        return [$this->php(), $this->extensions(), $this->laravel()];
    }

    /**
     * @return list<DiagnosticResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->check(), static fn (DiagnosticResult $result): bool => $result->isFailure()));
    }

    private function php(): DiagnosticResult
    {
        $details = ['version' => $this->phpVersion, 'minimum' => self::MinimumPhpVersion];

        return (version_compare($this->phpVersion, self::MinimumPhpVersion, '>=') && version_compare($this->phpVersion, '9.0.0', '<'))
            ? DiagnosticResult::passed('php.version', sprintf('PHP %s is supported.', $this->phpVersion), $details)
            : DiagnosticResult::failed('php.version', (version_compare($this->phpVersion, '9.0.0', '>=') ? 'PHP must satisfy the supported ^8.3 version range.' : sprintf('PHP %s is older than the required %s.', $this->phpVersion, self::MinimumPhpVersion)), $details);
    }

    private function extensions(): DiagnosticResult
    {
        $loaded = array_map(strtolower(...), $this->loadedExtensions ?? get_loaded_extensions());
        $missing = array_values(array_diff(self::RequiredExtensions, $loaded));

        return $missing === []
            ? DiagnosticResult::passed('php.extensions', 'All required PHP extensions are loaded.', ['required' => self::RequiredExtensions])
            : DiagnosticResult::failed('php.extensions', sprintf('Missing PHP extension(s): %s.', implode(', ', $missing)), ['missing' => $missing]);
    }

    private function laravel(): DiagnosticResult
    {
        $version = $this->app->version();
        $details = ['version' => $version, 'minimum' => self::MinimumLaravelVersion];

        return (version_compare($version, self::MinimumLaravelVersion, '>=') && version_compare($version, '14.0.0', '<'))
            ? DiagnosticResult::passed('laravel.version', sprintf('Laravel %s is supported.', $version), $details)
            : DiagnosticResult::failed('laravel.version', (version_compare($version, '14.0.0', '>=') ? 'Laravel must satisfy the supported ^13.0 version range.' : sprintf('Laravel %s is older than the required %s.', $version, self::MinimumLaravelVersion)), $details);
    }
}
