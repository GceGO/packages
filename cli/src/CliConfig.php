<?php

declare(strict_types=1);

namespace Gcego\Cli;

use Gcego\Cli\Contracts\DefinitionInspector;
use Gcego\Cli\Contracts\DiagnosticCheck;
use Gcego\Cli\Contracts\Generator;
use InvalidArgumentException;

/**
 * gcego-cli.php, validated.
 */
final readonly class CliConfig
{
    public const array PathKinds = ['blueprint', 'module', 'page', 'panel', 'layout', 'navigation', 'widget', 'workflow', 'settings', 'settings-page', 'analytics', 'notification', 'support', 'test'];

    /**
     * @param  array<string, string>  $paths
     * @param  list<class-string<Generator>>  $generators
     * @param  list<class-string<DefinitionInspector>>  $inspectors
     * @param  list<class-string<DiagnosticCheck>>  $checks
     */
    public function __construct(
        public bool $enabled = true,
        public array $paths = [],
        public bool $generateTests = false,
        public array $generators = [],
        public ?string $stubPath = null,
        public array $inspectors = [],
        public int $limit = 200,
        public bool $diagnostics = true,
        public array $checks = [],
    ) {}

    /**
     * @param  array<mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $paths = [];

        foreach (is_array($config['paths'] ?? null) ? $config['paths'] : [] as $kind => $path) {
            if (! in_array($kind, self::PathKinds, true)) {
                throw new InvalidArgumentException(sprintf('gcego-cli.paths.%s is not a known kind (%s).', (string) $kind, implode(', ', self::PathKinds)));
            }

            if ($path === null) {
                continue;
            }

            if (! is_string($path) || $path === '' || str_contains($path, '..')) {
                throw new InvalidArgumentException(sprintf('gcego-cli.paths.%s must be a directory path without "..".', $kind));
            }

            $paths[$kind] = rtrim($path, '/');
        }

        $generators = self::section($config, 'generators');
        $stubs = self::section($config, 'stubs');
        $inspection = self::section($config, 'inspection');
        $diagnostics = self::section($config, 'diagnostics');
        $limit = $inspection['limit'] ?? 200;

        if (! is_int($limit) || $limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('gcego-cli.inspection.limit must be an integer between 1 and 10000.');
        }

        $stubPath = $stubs['custom_path'] ?? null;

        return new self(
            self::bool($config, 'enabled', true),
            $paths,
            self::bool($generators, 'generate_tests', false, 'generators.'),
            self::classes($generators['custom'] ?? [], Generator::class, 'generators.custom'),
            is_string($stubPath) && $stubPath !== '' ? rtrim($stubPath, '/') : null,
            self::classes($inspection['inspectors'] ?? [], DefinitionInspector::class, 'inspection.inspectors'),
            $limit,
            self::bool($diagnostics, 'enabled', true, 'diagnostics.'),
            self::classes($diagnostics['checks'] ?? [], DiagnosticCheck::class, 'diagnostics.checks'),
        );
    }

    /**
     * @param  array<mixed>  $config
     * @return array<mixed>
     */
    private static function section(array $config, string $key): array
    {
        $section = $config[$key] ?? [];

        if (! is_array($section)) {
            throw new InvalidArgumentException(sprintf('gcego-cli.%s must be an array.', $key));
        }

        return $section;
    }

    /**
     * @param  array<mixed>|mixed  $config
     */
    private static function bool(mixed $config, string $key, bool $default, string $prefix = ''): bool
    {
        $value = is_array($config) ? ($config[$key] ?? $default) : $default;

        if (! is_bool($value)) {
            throw new InvalidArgumentException(sprintf('gcego-cli.%s%s must be true or false.', $prefix, $key));
        }

        return $value;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return list<class-string<T>>
     */
    private static function classes(mixed $classes, string $contract, string $key): array
    {
        if (! is_array($classes)) {
            throw new InvalidArgumentException(sprintf('gcego-cli.%s must be a list of classes.', $key));
        }

        $valid = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! is_subclass_of($class, $contract)) {
                throw new InvalidArgumentException(sprintf('gcego-cli.%s: %s does not implement %s.', $key, is_string($class) ? $class : get_debug_type($class), $contract));
            }

            $valid[] = $class;
        }

        return array_values(array_unique($valid));
    }
}
