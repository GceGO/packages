<?php

declare(strict_types=1);

namespace Gcego\Pages;

use Gcego\Pages\Contracts\PageLifecycleHook;
use Gcego\Pages\Exceptions\InvalidPageException;
use Gcego\Pages\Presets\PagePresets;
use Gcego\Pages\Records\RecordKey;

/**
 * Validated gcego-pages configuration (a container singleton, built once).
 */
final readonly class PagesConfig
{
    public const string RoutePrefixPattern = '/^(?:[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*)?$/';

    public const string NamePrefixPattern = '/^(?:[a-z0-9_-]+\.)*$/';

    public const string MiddlewarePattern = '/^[A-Za-z0-9_.:,\\\\-]{1,190}$/';

    /**
     * @param  list<string>  $middleware
     * @param  list<string>  $vite
     * @param  list<class-string<PageLifecycleHook>>  $hooks
     */
    public function __construct(
        public bool $routing = true,
        public string $prefix = 'admin',
        public string $namePrefix = 'gcego.',
        public array $middleware = ['web', 'auth'],
        public ?string $domain = null,
        public string $defaultAccess = 'deny',
        public string $defaultWidth = 'standard',
        public string $recordKey = 'key',
        public bool $breadcrumbs = true,
        public ?string $breadcrumbHome = null,
        public ?string $breadcrumbNavigation = null,
        public bool $navigationAutoRegister = false,
        public string $navigationTarget = 'admin',
        public string $layout = 'gcego-pages::layouts.app',
        public array $vite = [],
        public ?string $layoutNavigation = null,
        public bool $wireNavigate = false,
        public array $hooks = [],
        public int $maxComponents = 300,
        public int $maxLivewireHosts = 8,
    ) {
        if (preg_match(self::RoutePrefixPattern, trim($prefix, '/')) !== 1) {
            throw InvalidPageException::invalid('gcego-pages.routing.prefix', 'use URL segments of letters, digits, "-" and "_" (for example "admin").');
        }

        if (preg_match(self::NamePrefixPattern, $namePrefix) !== 1) {
            throw InvalidPageException::invalid('gcego-pages.routing.name_prefix', 'use lowercase dotted segments ending with "." (for example "gcego."), or "".');
        }

        foreach ($middleware as $entry) {
            if (preg_match(self::MiddlewarePattern, $entry) !== 1) {
                throw InvalidPageException::invalid('gcego-pages.routing.middleware', sprintf('"%s" is not a middleware name or alias.', mb_substr($entry, 0, 60)));
            }
        }

        if ($domain !== null && preg_match('/^[A-Za-z0-9.{}-]{1,253}$/', $domain) !== 1) {
            throw InvalidPageException::invalid('gcego-pages.routing.domain', 'use a host name such as "admin.example.com".');
        }

        if (! in_array($defaultAccess, ['deny', 'authenticated', 'public'], true)) {
            throw InvalidPageException::invalid('gcego-pages.authorization.default', 'use "deny", "authenticated" or "public".');
        }

        if (! isset(PagePresets::Widths[$defaultWidth])) {
            throw InvalidPageException::invalid('gcego-pages.pages.default_width', 'use '.implode(', ', array_keys(PagePresets::Widths)).'.');
        }

        if (! isset(RecordKey::Patterns[$recordKey])) {
            throw InvalidPageException::invalid('gcego-pages.pages.record_key', 'use '.implode(', ', array_keys(RecordKey::Patterns)).'.');
        }

        foreach (['gcego-pages.breadcrumbs.home' => $breadcrumbHome] as $key => $page) {
            if ($page !== null && preg_match(Page::NamePattern, $page) !== 1) {
                throw InvalidPageException::invalid($key, 'use a page name such as "dashboard".');
            }
        }

        foreach (['gcego-pages.breadcrumbs.navigation' => $breadcrumbNavigation, 'gcego-pages.navigation.target' => $navigationTarget, 'gcego-pages.rendering.navigation' => $layoutNavigation] as $key => $navigation) {
            if ($navigation !== null && preg_match('/^(?:navigation:)?[a-z][a-z0-9_-]{0,63}$/', $navigation) !== 1) {
                throw InvalidPageException::invalid($key, 'use a navigation name such as "admin".');
            }
        }

        if (preg_match('/^[A-Za-z0-9_.:\/-]{1,190}$/', $layout) !== 1) {
            throw InvalidPageException::invalid('gcego-pages.rendering.layout', 'use a Blade view name.');
        }

        foreach ($vite as $entry) {
            if (preg_match('/^[A-Za-z0-9_.\/-]{1,190}$/', $entry) !== 1 || str_contains($entry, '..')) {
                throw InvalidPageException::invalid('gcego-pages.rendering.vite', sprintf('"%s" is not a Vite entry path.', mb_substr($entry, 0, 60)));
            }
        }

        foreach ($hooks as $hook) {
            if (! is_subclass_of($hook, PageLifecycleHook::class)) {
                throw InvalidPageException::invalid('gcego-pages.lifecycle.hooks', sprintf('%s is not a %s.', $hook, PageLifecycleHook::class));
            }
        }

        if ($maxComponents < 1 || $maxLivewireHosts < 1) {
            throw InvalidPageException::invalid('gcego-pages.diagnostics', 'limits must be positive.');
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
        $bool = static fn (string $section, string $key, bool $default): bool => is_bool($value = $read($section, $key, $default)) ? $value : throw InvalidPageException::invalid("gcego-pages.$section.$key", 'must be true or false.');
        $int = static fn (string $section, string $key, int $default): int => is_int($value = $read($section, $key, $default)) ? $value : throw InvalidPageException::invalid("gcego-pages.$section.$key", 'must be a whole number.');
        $string = static fn (string $section, string $key, string $default): string => is_string($value = $read($section, $key, $default)) ? $value : throw InvalidPageException::invalid("gcego-pages.$section.$key", 'must be a string.');
        $nullable = static fn (string $section, string $key): ?string => is_string($value = $read($section, $key, null)) || $value === null ? $value : throw InvalidPageException::invalid("gcego-pages.$section.$key", 'must be a string or null.');
        $strings = static function (string $section, string $key, array $default) use ($read): array {
            $value = $read($section, $key, $default);

            if (! is_array($value) || array_filter($value, static fn (mixed $entry): bool => ! is_string($entry)) !== []) {
                throw InvalidPageException::invalid("gcego-pages.$section.$key", 'must be a list of strings.');
            }

            /** @var list<string> */
            return array_values($value);
        };

        /** @var list<class-string<PageLifecycleHook>> $hooks */
        $hooks = $strings('lifecycle', 'hooks', []);

        return new self(
            $bool('routing', 'enabled', true),
            trim($string('routing', 'prefix', 'admin'), '/'),
            $string('routing', 'name_prefix', 'gcego.'),
            $strings('routing', 'middleware', ['web', 'auth']),
            $nullable('routing', 'domain'),
            $string('authorization', 'default', 'deny'),
            $string('pages', 'default_width', 'standard'),
            $string('pages', 'record_key', 'key'),
            $bool('breadcrumbs', 'enabled', true),
            $nullable('breadcrumbs', 'home'),
            $nullable('breadcrumbs', 'navigation'),
            $bool('navigation', 'auto_register', false),
            $string('navigation', 'target', 'admin'),
            $string('rendering', 'layout', 'gcego-pages::layouts.app'),
            $strings('rendering', 'vite', []),
            $nullable('rendering', 'navigation'),
            $bool('rendering', 'wire_navigate', false),
            $hooks,
            $int('diagnostics', 'max_components', 300),
            $int('diagnostics', 'max_livewire_hosts', 8),
        );
    }
}
