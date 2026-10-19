<?php

declare(strict_types=1);

namespace Gcego\Luminaries;

use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\Presets\EducationPreset;
use Gcego\Luminaries\Presets\FrameworkPreset;

/**
 * The validated luminaries configuration.
 *
 * Built once per application from config('luminaries'); every value is
 * checked here so misconfiguration fails at boot with a precise message
 * instead of producing broken markup later.
 */
final readonly class LuminariesConfig
{
    public const array Behaviors = ['exception', 'fallback', 'placeholder', 'empty'];

    public const array Presets = [
        FrameworkPreset::Name => FrameworkPreset::Aliases,
        EducationPreset::Name => EducationPreset::Aliases,
    ];

    private const string ClassPattern = '/^[A-Za-z0-9_:\-\/\.\[\]\(\)%#!@ ]*$/';

    /**
     * @param  array<string, array{set: string, variant: ?string, label: string, style: string, stroke: bool}>  $providers
     * @param  array<string, array{path: string, label: string}>  $collections
     * @param  list<string>  $presets
     * @param  array<string, string>  $aliases
     * @param  array<string, string>  $sizes
     * @param  array<string, string>  $tones
     * @param  list<string>  $trustedRoots
     * @param  list<string>  $explorerMiddleware
     */
    public function __construct(
        public string $defaultProvider = 'heroicon',
        public array $providers = [],
        public array $collections = [],
        public array $presets = [FrameworkPreset::Name],
        public array $aliases = [],
        public ?string $defaultSize = 'md',
        public array $sizes = [],
        public array $tones = [],
        public string $class = '',
        public string $missingBehavior = 'exception',
        public ?string $fallback = null,
        public bool $logMissing = true,
        public array $trustedRoots = [],
        public ?string $cachePath = null,
        public bool $bladeIconsComponents = false,
        public bool $explorerEnabled = false,
        public string $explorerPath = 'gcego/icons',
        public array $explorerMiddleware = ['web'],
        public int $explorerPerPage = 120,
    ) {}

    /**
     * @param  array<mixed>  $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(array $config): self
    {
        $providers = [];

        foreach (self::map($config, 'providers') as $name => $definition) {
            if (! is_array($definition)) {
                throw InvalidConfigurationException::invalid("providers.{$name}", 'must be an array.');
            }

            if (($definition['enabled'] ?? true) === false) {
                continue;
            }

            $set = $definition['set'] ?? null;
            $variant = $definition['variant'] ?? null;

            if (preg_match(IconName::ProviderPattern, $name) !== 1 || ! is_string($set) || $set === '') {
                throw InvalidConfigurationException::invalid("providers.{$name}", 'needs a lowercase name and a Blade Icons "set".');
            }

            if ($variant !== null && (! is_string($variant) || preg_match('/^[a-z0-9]{1,8}$/', $variant) !== 1)) {
                throw InvalidConfigurationException::invalid("providers.{$name}.variant", 'must be null or a short lowercase file prefix such as "o".');
            }

            $providers[$name] = [
                'set' => $set,
                'variant' => $variant,
                'label' => self::string($definition, 'label', $name),
                'style' => self::string($definition, 'style', 'outline'),
                'stroke' => (bool) ($definition['stroke'] ?? false),
            ];
        }

        $collections = [];

        foreach (self::map($config, 'collections') as $name => $definition) {
            $definition = is_string($definition) ? ['path' => $definition] : $definition;

            if (preg_match(IconName::ProviderPattern, $name) !== 1 || ! is_array($definition) || ! is_string($definition['path'] ?? null)) {
                throw InvalidConfigurationException::invalid("collections.{$name}", 'needs a lowercase name and a "path".');
            }

            if (isset($providers[$name]) || $name === Providers\InlinePathProvider::Name) {
                throw InvalidConfigurationException::invalid("collections.{$name}", 'the name is already used by a provider.');
            }

            $collections[$name] = ['path' => $definition['path'], 'label' => self::string($definition, 'label', $name)];
        }

        $presets = [];

        foreach ((array) ($config['presets'] ?? [FrameworkPreset::Name]) as $preset) {
            if (! is_string($preset) || ! isset(self::Presets[$preset])) {
                throw InvalidConfigurationException::invalid('presets', sprintf('unknown preset "%s"; available: %s.', is_string($preset) ? $preset : get_debug_type($preset), implode(', ', array_keys(self::Presets))));
            }

            $presets[] = $preset;
        }

        $aliases = [];

        foreach (self::map($config, 'aliases') as $alias => $target) {
            if (! is_string($target)) {
                throw InvalidConfigurationException::invalid("aliases.{$alias}", 'must be an icon identifier string.');
            }

            $aliases[$alias] = $target;
        }

        $sizes = self::classMap($config, 'sizes');
        $tones = self::classMap($config, 'tones');
        $defaultSize = array_key_exists('default_size', $config) ? $config['default_size'] : 'md';

        if ($defaultSize !== null && (! is_string($defaultSize) || ! isset($sizes[$defaultSize]))) {
            throw InvalidConfigurationException::invalid('default_size', sprintf('must be null or one of: %s.', implode(', ', array_keys($sizes))));
        }

        $missing = is_array($config['missing'] ?? null) ? $config['missing'] : [];
        $behavior = $missing['behavior'] ?? 'exception';

        if (! is_string($behavior) || ! in_array($behavior, self::Behaviors, true)) {
            throw InvalidConfigurationException::invalid('missing.behavior', sprintf('must be one of: %s.', implode(', ', self::Behaviors)));
        }

        $fallback = $missing['fallback'] ?? null;

        if ($fallback !== null && (! is_string($fallback) || ! IconName::isValid($fallback))) {
            throw InvalidConfigurationException::invalid('missing.fallback', 'must be null or an icon identifier.');
        }

        $defaultProvider = $config['default_provider'] ?? 'heroicon';

        if (! is_string($defaultProvider) || preg_match(IconName::ProviderPattern, $defaultProvider) !== 1) {
            throw InvalidConfigurationException::invalid('default_provider', 'must be a provider name.');
        }

        $class = $config['class'] ?? '';

        if (! is_string($class) || preg_match(self::ClassPattern, $class) !== 1) {
            throw InvalidConfigurationException::invalid('class', 'must be a string of CSS class names.');
        }

        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $roots = array_values(array_filter((array) ($security['trusted_roots'] ?? []), static fn (mixed $root): bool => is_string($root) && $root !== ''));
        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $explorer = is_array($config['explorer'] ?? null) ? $config['explorer'] : [];
        $explorerPath = trim(is_string($explorer['path'] ?? null) ? $explorer['path'] : 'gcego/icons', '/');

        if (preg_match('/^[A-Za-z0-9\-_\/]{1,100}$/', $explorerPath) !== 1) {
            throw InvalidConfigurationException::invalid('explorer.path', 'must be a URL path such as "gcego/icons".');
        }

        return new self(
            defaultProvider: $defaultProvider,
            providers: $providers,
            collections: $collections,
            presets: $presets,
            aliases: $aliases,
            defaultSize: $defaultSize,
            sizes: $sizes,
            tones: $tones,
            class: trim($class),
            missingBehavior: $behavior,
            fallback: $fallback,
            logMissing: (bool) ($missing['log'] ?? true),
            trustedRoots: $roots,
            cachePath: is_string($cache['path'] ?? null) && $cache['path'] !== '' ? $cache['path'] : null,
            bladeIconsComponents: (bool) ($config['blade_icons_components'] ?? false),
            explorerEnabled: (bool) ($explorer['enabled'] ?? false),
            explorerPath: $explorerPath,
            explorerMiddleware: array_values(array_filter((array) ($explorer['middleware'] ?? ['web']), is_string(...))),
            explorerPerPage: max(12, min(600, is_numeric($explorer['per_page'] ?? null) ? (int) $explorer['per_page'] : 120)),
        );
    }

    /**
     * Preset aliases in order, then configured aliases.
     *
     * @return array<string, array{target: string, origin: string}>
     */
    public function allAliases(): array
    {
        $aliases = [];

        foreach ($this->presets as $preset) {
            foreach (self::Presets[$preset] as $alias => $target) {
                $aliases[$alias] = ['target' => $target, 'origin' => $preset];
            }
        }

        foreach ($this->aliases as $alias => $target) {
            $aliases[$alias] = ['target' => $target, 'origin' => 'config'];
        }

        return $aliases;
    }

    /**
     * A stable fingerprint of everything the icon manifest depends on.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', serialize([$this->defaultProvider, $this->providers, $this->collections, $this->presets, $this->aliases]));
    }

    /**
     * @param  array<mixed>  $config
     * @return array<string, mixed>
     */
    private static function map(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (! is_array($value)) {
            throw InvalidConfigurationException::invalid($key, 'must be an array.');
        }

        $map = [];

        foreach ($value as $name => $item) {
            if (! is_string($name)) {
                throw InvalidConfigurationException::invalid($key, 'must be keyed by name.');
            }

            $map[$name] = $item;
        }

        return $map;
    }

    /**
     * @param  array<mixed>  $config
     * @return array<string, string>
     */
    private static function classMap(array $config, string $key): array
    {
        $map = [];

        foreach (self::map($config, $key) as $token => $classes) {
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $token) !== 1 || ! is_string($classes) || preg_match(self::ClassPattern, $classes) !== 1) {
                throw InvalidConfigurationException::invalid("{$key}.{$token}", 'needs a lowercase token and a string of CSS classes.');
            }

            $map[$token] = trim($classes);
        }

        return $map;
    }

    /**
     * @param  array<mixed>  $definition
     */
    private static function string(array $definition, string $key, string $default): string
    {
        $value = $definition[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
