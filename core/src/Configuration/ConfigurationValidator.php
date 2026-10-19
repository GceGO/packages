<?php

declare(strict_types=1);

namespace Gcego\Core\Configuration;

use Gcego\Core\Support\Identifier;

/**
 * Validates and interprets the raw config('gcego') array without throwing.
 *
 * - Booleans accept true/false, 0/1 and the strings env() leaves behind
 *   ("1", "0", "true", "false", "yes", "no", "on", "off").
 * - null and "" fall back to the default value.
 * - Unknown keys are warnings, not errors, so older published config files keep working.
 * - Messages name keys and types only, never values.
 *
 * @phpstan-type Values array{enabled: bool, name: string, auto_boot: bool, environment_checks: bool, disabled_packages: list<string>, disabled_extensions: list<string>}
 */
final class ConfigurationValidator
{
    public const int MaxNameLength = 120;

    private const array KnownKeys = ['enabled', 'name', 'auto_boot', 'environment_checks', 'packages', 'extensions'];

    private const array KnownSectionKeys = ['disabled'];

    /**
     * @param  array<mixed>  $config
     * @return array{errors: list<string>, warnings: list<string>, values: Values}
     */
    public function validate(array $config): array
    {
        $errors = [];
        $warnings = [];

        foreach (array_keys($config) as $key) {
            if (! in_array($key, self::KnownKeys, true)) {
                $warnings[] = sprintf('gcego.%s is not a known option and is ignored.', $key);
            }
        }

        $values = [
            'enabled' => $this->boolean($config, 'enabled', true, $errors),
            'name' => $this->name($config, $errors),
            'auto_boot' => $this->boolean($config, 'auto_boot', true, $errors),
            'environment_checks' => $this->boolean($config, 'environment_checks', true, $errors),
            'disabled_packages' => $this->identifiers($config, 'packages', 'Composer package names such as "gcego/tables"', Identifier::isPackage(...), $errors, $warnings),
            'disabled_extensions' => $this->identifiers($config, 'extensions', 'extension identifiers such as "tables.export"', Identifier::isName(...), $errors, $warnings),
        ];

        return ['errors' => $errors, 'warnings' => $warnings, 'values' => $values];
    }

    /**
     * @param  array<mixed>  $config
     * @param  list<string>  $errors
     */
    private function boolean(array $config, string $key, bool $default, array &$errors): bool
    {
        $value = $config[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return $value === 1;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        $errors[] = sprintf('gcego.%s must be a boolean, %s given.', $key, $this->describeType($value));

        return $default;
    }

    /**
     * @param  array<mixed>  $config
     * @param  list<string>  $errors
     */
    private function name(array $config, array &$errors): string
    {
        $default = 'GCEGO Admin Studio';
        $value = $config['name'] ?? null;

        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > self::MaxNameLength) {
            $errors[] = sprintf('gcego.name must be a non-empty string of at most %d characters, %s given.', self::MaxNameLength, $this->describeType($value));

            return $default;
        }

        return trim($value);
    }

    /**
     * @param  array<mixed>  $config
     * @param  callable(mixed): bool  $isValid
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function identifiers(array $config, string $section, string $expected, callable $isValid, array &$errors, array &$warnings): array
    {
        $options = $config[$section] ?? [];

        if (! is_array($options)) {
            $errors[] = sprintf('gcego.%s must be an array, %s given.', $section, $this->describeType($options));

            return [];
        }

        foreach (array_keys($options) as $key) {
            if (! in_array($key, self::KnownSectionKeys, true)) {
                $warnings[] = sprintf('gcego.%s.%s is not a known option and is ignored.', $section, $key);
            }
        }

        $disabled = $options['disabled'] ?? [];

        if (! is_array($disabled) || ! array_is_list($disabled)) {
            $errors[] = sprintf('gcego.%s.disabled must be a list of %s, %s given.', $section, $expected, $this->describeType($disabled));

            return [];
        }

        $valid = [];

        foreach ($disabled as $index => $id) {
            if (! $isValid($id) || ! is_string($id)) {
                $errors[] = sprintf('gcego.%s.disabled.%d must be one of %s, %s given.', $section, $index, $expected, $this->describeType($id));

                continue;
            }

            $valid[] = $id;
        }

        return array_values(array_unique($valid));
    }

    private function describeType(mixed $value): string
    {
        return match (true) {
            is_string($value) => 'a string',
            is_array($value) => 'an array',
            default => get_debug_type($value),
        };
    }
}
