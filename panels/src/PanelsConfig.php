<?php

declare(strict_types=1);

namespace Gcego\Panels;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\Exceptions\InvalidPanelException;

/**
 * Validated gcego-panels configuration (a container singleton, plain data).
 */
final readonly class PanelsConfig
{
    /**
     * @param  list<string>  $middleware
     * @param  array<string, string>  $userMenu  Label => route name.
     * @param  list<string>|null  $vite
     * @param  list<class-string<PanelExtension>>  $extensions
     */
    public function __construct(
        public string $namePrefix = 'gcego.',
        public array $middleware = ['web'],
        public bool $registerPageRoutes = true,
        public ?string $guard = null,
        public string $defaultLayout = 'sidebar',
        public ?string $defaultTheme = null,
        public bool $collapsibleSidebar = true,
        public array $userMenu = [],
        public ?array $vite = null,
        public array $extensions = [],
    ) {
        foreach ($extensions as $extension) {
            if (! is_a($extension, PanelExtension::class, true)) {
                throw InvalidPanelException::invalid('gcego-panels.extensions', sprintf('%s does not implement %s.', $extension, PanelExtension::class));
            }
        }

        if (preg_match('/^(?:[a-z0-9_-]+\.)*$/', $namePrefix) !== 1) {
            throw InvalidPanelException::invalid('gcego-panels.routing.name_prefix', 'use dotted lowercase segments ending with "." such as "gcego.", or "".');
        }

        foreach ($middleware as $entry) {
            if (preg_match('/^[A-Za-z0-9_.:,\\\\-]{1,190}$/', $entry) !== 1) {
                throw InvalidPanelException::invalid('gcego-panels.routing.middleware', sprintf('"%s" is not a middleware name.', mb_substr($entry, 0, 60)));
            }
        }

        if ($guard !== null && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $guard) !== 1) {
            throw InvalidPanelException::invalid('gcego-panels.security.guard', 'use a guard name or null.');
        }

        if (! in_array($defaultLayout, Panel::Layouts, true)) {
            throw InvalidPanelException::invalid('gcego-panels.appearance.default_layout', 'use one of '.implode(', ', Panel::Layouts).'.');
        }

        if ($defaultTheme !== null && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $defaultTheme) !== 1) {
            throw InvalidPanelException::invalid('gcego-panels.appearance.default_theme', 'use a theme name or null.');
        }

        foreach ($userMenu as $label => $route) {
            if (trim($label) === '' || preg_match('/^[A-Za-z0-9_.:-]{1,190}$/', $route) !== 1) {
                throw InvalidPanelException::invalid('gcego-panels.user_menu', 'use label => route name pairs.');
            }
        }

        foreach ($vite ?? [] as $entry) {
            if (preg_match('/^[A-Za-z0-9_.\/-]{1,190}$/', $entry) !== 1 || str_contains($entry, '..')) {
                throw InvalidPanelException::invalid('gcego-panels.assets.vite', sprintf('"%s" is not a Vite entry path.', mb_substr($entry, 0, 60)));
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $read = static function (string $section, string $key, mixed $default) use ($config): mixed {
            $values = $config[$section] ?? [];

            return is_array($values) && array_key_exists($key, $values) ? $values[$key] : $default;
        };
        $bool = static fn (string $section, string $key, bool $default): bool => is_bool($value = $read($section, $key, $default)) ? $value : throw InvalidPanelException::invalid("gcego-panels.$section.$key", 'must be true or false.');
        $string = static fn (string $section, string $key, string $default): string => is_string($value = $read($section, $key, $default)) ? $value : throw InvalidPanelException::invalid("gcego-panels.$section.$key", 'must be a string.');
        $nullable = static fn (string $section, string $key): ?string => is_string($value = $read($section, $key, null)) || $value === null ? $value : throw InvalidPanelException::invalid("gcego-panels.$section.$key", 'must be a string or null.');
        $strings = static function (mixed $value, string $key): array {
            if (! is_array($value) || array_filter($value, static fn (mixed $entry): bool => ! is_string($entry)) !== []) {
                throw InvalidPanelException::invalid('gcego-panels.'.$key, 'must be a list of strings.');
            }

            /** @var list<string> */
            return array_values($value);
        };
        $menu = $config['user_menu'] ?? [];

        if (! is_array($menu) || array_filter($menu, static fn (mixed $route, mixed $label): bool => ! is_string($route) || ! is_string($label), ARRAY_FILTER_USE_BOTH) !== []) {
            throw InvalidPanelException::invalid('gcego-panels.user_menu', 'use label => route name pairs.');
        }

        /** @var array<string, string> $menu */
        $vite = $read('assets', 'vite', null);

        return new self(
            namePrefix: $string('routing', 'name_prefix', 'gcego.'),
            middleware: $strings($read('routing', 'middleware', ['web']), 'routing.middleware'),
            registerPageRoutes: $bool('routing', 'register_page_routes', true),
            guard: $nullable('security', 'guard'),
            defaultLayout: $string('appearance', 'default_layout', 'sidebar'),
            defaultTheme: $nullable('appearance', 'default_theme'),
            collapsibleSidebar: $bool('navigation', 'collapsible_sidebar', true),
            userMenu: $menu,
            vite: $vite === null ? null : $strings($vite, 'assets.vite'),
            extensions: array_map(static fn (string $class): string => is_a($class, PanelExtension::class, true) ? $class : throw InvalidPanelException::invalid('gcego-panels.extensions', sprintf('%s does not implement %s.', $class, PanelExtension::class)), $strings($config['extensions'] ?? [], 'extensions')),
        );
    }
}
