<?php

declare(strict_types=1);

namespace Gcego\Studio\Configuration;

use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Exceptions\StudioConfigurationException;

/**
 * Validated gcego-studio configuration (a container singleton, plain data).
 */
final readonly class StudioConfiguration
{
    public const array Layouts = ['sidebar', 'topbar', 'compact'];

    /**
     * @param  list<string>|null  $vite
     * @param  list<class-string<StudioExtension>>  $extensions
     */
    public function __construct(
        public bool $enabled = true,
        public bool $discoverPanels = true,
        public bool $discoverModules = true,
        public bool $discoverPages = true,
        public ?string $defaultLayout = null,
        public ?string $defaultTheme = null,
        public ?array $vite = null,
        public ?string $cssEntry = null,
        public ?string $jsEntry = null,
        public ?string $manifest = null,
        public bool $requireAuthentication = true,
        public bool $requireAuthorization = true,
        public bool $productionDetails = false,
        public array $extensions = [],
        public string $installPanel = 'admin',
        public string $installAbility = 'access-admin',
    ) {
        if ($defaultLayout !== null && ! in_array($defaultLayout, self::Layouts, true)) {
            throw StudioConfigurationException::invalid('appearance.default_layout', sprintf('must be one of %s, or null.', implode(', ', self::Layouts)));
        }

        if ($defaultTheme !== null && preg_match('/^[a-z][a-z0-9-]{0,63}$/', $defaultTheme) !== 1) {
            throw StudioConfigurationException::invalid('appearance.default_theme', 'must be a theme name such as "default", or null.');
        }

        foreach ($vite ?? [] as $entry) {
            if (preg_match('#^[A-Za-z0-9_\-./]+\.(css|js|ts)$#', $entry) !== 1 || str_contains($entry, '..')) {
                throw StudioConfigurationException::invalid('assets.vite', sprintf('"%s" is not a Vite entry such as resources/css/app.css.', $entry));
            }
        }

        foreach ($extensions as $extension) {
            if (! class_exists($extension) || ! is_subclass_of($extension, StudioExtension::class)) {
                throw StudioConfigurationException::invalid('extensions', sprintf('"%s" is not a class implementing %s.', $extension, StudioExtension::class));
            }
        }

        if (preg_match('/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/', $installPanel) !== 1) {
            throw StudioConfigurationException::invalid('install.panel', 'must be a lowercase panel name such as "admin".');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $installAbility) !== 1) {
            throw StudioConfigurationException::invalid('install.ability', 'must be a Gate ability name such as "access-admin".');
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $get = static fn (string $key, mixed $default): mixed => data_get($config, $key, $default);
        $bool = static fn (string $key, bool $default): bool => is_bool($value = $get($key, $default)) ? $value : throw StudioConfigurationException::invalid($key, 'must be true or false.');
        $nullable = static fn (string $key): ?string => is_string($value = $get($key, null)) || $value === null ? ($value === '' ? null : $value) : throw StudioConfigurationException::invalid($key, 'must be a string or null.');
        $string = static fn (string $key, string $default): string => is_string($value = $get($key, $default)) ? $value : throw StudioConfigurationException::invalid($key, 'must be a string.');
        $list = static function (string $key) use ($get): ?array {
            $value = $get($key, null);

            if ($value === null) {
                return null;
            }

            if (! is_array($value) || ! array_is_list($value) || array_filter($value, static fn (mixed $entry): bool => ! is_string($entry)) !== []) {
                throw StudioConfigurationException::invalid($key, 'must be a list of strings, or null.');
            }

            /** @var list<string> $value */
            return $value;
        };

        /** @var list<class-string<StudioExtension>> $extensions */
        $extensions = $list('extensions') ?? [];

        return new self(
            enabled: $bool('enabled', true),
            discoverPanels: $bool('discovery.panels', true),
            discoverModules: $bool('discovery.modules', true),
            discoverPages: $bool('discovery.pages', true),
            defaultLayout: $nullable('appearance.default_layout'),
            defaultTheme: $nullable('appearance.default_theme'),
            vite: $list('assets.vite'),
            cssEntry: $nullable('assets.css'),
            jsEntry: $nullable('assets.js'),
            manifest: $nullable('assets.manifest'),
            requireAuthentication: $bool('security.require_authentication', true),
            requireAuthorization: $bool('security.require_authorization', true),
            productionDetails: $bool('diagnostics.production_details', false),
            extensions: $extensions,
            installPanel: $string('install.panel', 'admin'),
            installAbility: $string('install.ability', 'access-admin'),
        );
    }
}
