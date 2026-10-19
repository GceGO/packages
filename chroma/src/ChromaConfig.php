<?php

declare(strict_types=1);

namespace Gcego\Chroma;

use Gcego\Chroma\Exceptions\InvalidThemeException;

/**
 * A validated, typed view of config('gcego-chroma').
 *
 * Colors, appearance (light/dark/system) and accents are configured in
 * config/gcego-swatches.php (gcego/swatches). Old gcego-chroma.appearance and
 * gcego-chroma.accents values are still honoured there, with a deprecation notice.
 */
final readonly class ChromaConfig
{
    /**
     * @param  string  $theme  The default theme (gcego-chroma.theme, else gcego-swatches.theme).
     * @param  array<string, array<mixed>>  $themes  Styling theme configs by name (non-color tokens and presets).
     * @param  array<string, array<string, mixed>>  $overrides  Component class => slot => classes or ['mode' => ..., 'classes' => ...].
     */
    public function __construct(
        public string $theme = 'gcego',
        public array $themes = [],
        public array $overrides = [],
        public bool $strict = true,
    ) {}

    /**
     * @param  array<mixed>  $config
     * @param  string  $defaultTheme  Used when gcego-chroma.theme is null (gcego-swatches.theme).
     *
     * @throws InvalidThemeException
     */
    public static function fromArray(array $config, string $defaultTheme = 'gcego'): self
    {
        $theme = $config['theme'] ?? null;
        $themes = $config['themes'] ?? [];
        $overrides = $config['overrides'] ?? [];

        if ($theme !== null && (! is_string($theme) || $theme === '')) {
            throw InvalidThemeException::invalidConfig('theme', 'must be a theme name, or null to follow gcego-swatches.theme.');
        }

        if (! is_array($themes) || ! is_array($overrides)) {
            throw InvalidThemeException::invalidConfig('themes', '"themes" and "overrides" must be arrays.');
        }

        $validThemes = [];

        foreach ($themes as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                throw InvalidThemeException::invalidConfig('themes', 'must map theme names to arrays.');
            }

            $validThemes[$name] = $definition;
        }

        $validOverrides = [];

        foreach ($overrides as $target => $slots) {
            if (! is_string($target) || ! is_array($slots)) {
                throw InvalidThemeException::invalidConfig('overrides', 'must map component classes to slot arrays.');
            }

            $validSlots = [];

            foreach ($slots as $slot => $value) {
                if (! is_string($slot)) {
                    throw InvalidThemeException::invalidConfig('overrides.'.$target, 'slot names must be strings.');
                }

                $validSlots[$slot] = $value;
            }

            $validOverrides[$target] = $validSlots;
        }

        return new self(
            theme: $theme ?? $defaultTheme,
            themes: $validThemes,
            overrides: $validOverrides,
            strict: self::bool($config['strict'] ?? true, 'strict'),
        );
    }

    private static function bool(mixed $value, string $key): bool
    {
        $parsed = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (! is_bool($parsed)) {
            throw InvalidThemeException::invalidConfig($key, 'must be a boolean.');
        }

        return $parsed;
    }
}
