<?php

declare(strict_types=1);

namespace Gcego\Swatches;

use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Themes\AppearanceMode;

/**
 * A validated, typed view of config('gcego-swatches').
 */
final readonly class SwatchesConfig
{
    /**
     * @param  array<string, array<mixed>>  $themes
     * @param  array<string, string|array<int|string, string>>  $palettes
     * @param  list<string>|null  $accentNames
     * @param  list<string>  $explorerMiddleware
     */
    public function __construct(
        public string $theme = 'gcego',
        public array $themes = [],
        public array $palettes = [],
        public AppearanceMode $appearance = AppearanceMode::System,
        public bool $allowSwitching = true,
        public string $storageKey = 'gcego.appearance',
        public bool $accents = true,
        public ?array $accentNames = null,
        public string $accentStorageKey = 'gcego.accent',
        public bool $enforceContrast = false,
        public bool $staticPresets = false,
        public bool $apca = false,
        public bool $colorVision = false,
        public string $cachePath = 'bootstrap/cache/gcego-swatches.php',
        public bool $explorer = false,
        public string $explorerPath = 'gcego/colors',
        public array $explorerMiddleware = ['web'],
    ) {}

    /**
     * @param  array<mixed>  $config
     *
     * @throws InvalidThemeException
     */
    public static function fromArray(array $config): self
    {
        $appearance = self::section($config, 'appearance');
        $accents = self::section($config, 'accents');
        $validation = self::section($config, 'validation');
        $css = self::section($config, 'css');
        $cache = self::section($config, 'cache');
        $explorer = self::section($config, 'explorer');

        $theme = $config['theme'] ?? 'gcego';

        if (! is_string($theme) || $theme === '') {
            throw InvalidThemeException::invalidConfig('theme', 'must be a theme name.');
        }

        $mode = AppearanceMode::tryFrom(is_string($appearance['default'] ?? null) ? $appearance['default'] : 'system')
            ?? throw InvalidThemeException::invalidConfig('appearance.default', 'must be "light", "dark" or "system".');

        $themes = [];

        foreach (self::array($config, 'themes') as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                throw InvalidThemeException::invalidConfig('themes', 'must map theme names to arrays.');
            }

            $themes[$name] = $definition;
        }

        $palettes = [];

        foreach (self::array($config, 'palettes') as $name => $definition) {
            if (! is_string($name) || ! (is_string($definition) || is_array($definition))) {
                throw InvalidThemeException::invalidConfig('palettes', 'must map palette names to a color or to step => color arrays.');
            }

            if (is_array($definition)) {
                foreach ($definition as $value) {
                    if (! is_string($value)) {
                        throw InvalidThemeException::invalidConfig('palettes.'.$name, 'steps must be color strings.');
                    }
                }
            }

            /** @var string|array<int|string, string> $definition */
            $palettes[$name] = $definition;
        }

        $accentNames = $accents['only'] ?? null;

        if ($accentNames !== null && (! is_array($accentNames) || array_filter($accentNames, static fn (mixed $name): bool => ! is_string($name)) !== [])) {
            throw InvalidThemeException::invalidConfig('accents.only', 'must be a list of accent names, or null for all.');
        }

        $middleware = $explorer['middleware'] ?? ['web'];

        if (! is_array($middleware) || array_filter($middleware, static fn (mixed $item): bool => ! is_string($item)) !== []) {
            throw InvalidThemeException::invalidConfig('explorer.middleware', 'must be a list of middleware names.');
        }

        $explorerPath = $explorer['path'] ?? 'gcego/colors';
        $cachePath = $cache['path'] ?? 'bootstrap/cache/gcego-swatches.php';

        if (! is_string($explorerPath) || preg_match('#^[a-z0-9/_-]{1,100}$#i', $explorerPath) !== 1) {
            throw InvalidThemeException::invalidConfig('explorer.path', 'must be a URL path such as "gcego/colors".');
        }

        if (! is_string($cachePath) || $cachePath === '') {
            throw InvalidThemeException::invalidConfig('cache.path', 'must be a file path.');
        }

        /** @var list<string> $middleware */
        /** @var list<string>|null $accentNames */
        return new self(
            theme: $theme,
            themes: $themes,
            palettes: $palettes,
            appearance: $mode,
            allowSwitching: self::bool($appearance['allow_switching'] ?? true, 'appearance.allow_switching'),
            storageKey: self::storageKey($appearance['storage_key'] ?? 'gcego.appearance', 'appearance.storage_key'),
            accents: self::bool($accents['enabled'] ?? true, 'accents.enabled'),
            accentNames: $accentNames === null ? null : array_values($accentNames),
            accentStorageKey: self::storageKey($accents['storage_key'] ?? 'gcego.accent', 'accents.storage_key'),
            enforceContrast: self::bool($validation['enforce_contrast'] ?? false, 'validation.enforce_contrast'),
            staticPresets: self::bool($css['static_presets'] ?? false, 'css.static_presets'),
            apca: self::bool($validation['apca'] ?? false, 'validation.apca'),
            colorVision: self::bool($validation['color_vision'] ?? false, 'validation.color_vision'),
            cachePath: $cachePath,
            explorer: self::bool($explorer['enabled'] ?? false, 'explorer.enabled'),
            explorerPath: trim($explorerPath, '/'),
            explorerMiddleware: array_values($middleware),
        );
    }

    /**
     * @param  array<mixed>  $config
     * @return array<mixed>
     */
    private static function section(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (! is_array($value)) {
            throw InvalidThemeException::invalidConfig($key, 'must be an array.');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $config
     * @return array<mixed>
     */
    private static function array(array $config, string $key): array
    {
        return self::section($config, $key);
    }

    private static function storageKey(mixed $value, string $key): string
    {
        if (! is_string($value) || preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value) !== 1) {
            throw InvalidThemeException::invalidConfig($key, 'must be 1-64 letters, digits, ".", "_" or "-".');
        }

        return $value;
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
