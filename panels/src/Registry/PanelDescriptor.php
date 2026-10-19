<?php

declare(strict_types=1);

namespace Gcego\Panels\Registry;

use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Registry\Index\DefinitionDescriptor;

/**
 * A panel as the registry index describes it (no definition is loaded):
 * enough for routing, the switcher and navigation.
 */
final readonly class PanelDescriptor
{
    /**
     * @param  list<string>  $middleware
     * @param  list<string>  $modules
     * @param  list<string>  $pages
     * @param  list<string>  $unlisted  Referenced pages left out of the navigation.
     */
    public function __construct(
        public string $identity,
        public string $name,
        public string $title,
        public ?string $description,
        public string $path,
        public ?string $domain,
        public string $routeName,
        public ?string $guard,
        public array $middleware,
        public ?string $tenant,
        public bool $verified,
        public ?string $dashboard,
        public string $navigation,
        public array $modules,
        public array $pages,
        public int $order,
        public bool $hidden,
        public bool $declaresAccess,
        public array $unlisted = [],
    ) {}

    public static function fromDescriptor(DefinitionDescriptor $descriptor): self
    {
        $metadata = $descriptor->metadata;
        $string = static fn (string $key): string => is_string($metadata[$key] ?? null) ? $metadata[$key] : throw InvalidPanelException::invalid('Panel "'.$descriptor->key().'"', sprintf('its indexed "%s" is missing; rebuild the registry cache (php artisan gcego:registry-cache).', $key));
        $nullable = static fn (string $key): ?string => is_string($value = $metadata[$key] ?? null) && $value !== '' ? $value : null;

        return new self(
            identity: $descriptor->key(),
            name: $descriptor->name(),
            title: $string('title'),
            description: $nullable('description'),
            path: $string('path'),
            domain: $nullable('domain'),
            routeName: $string('route_name'),
            guard: $nullable('guard'),
            middleware: self::split($string('middleware'), '|'),
            tenant: $nullable('tenant'),
            verified: ($metadata['verified'] ?? false) === true,
            dashboard: $nullable('dashboard'),
            navigation: $string('navigation'),
            modules: self::split($string('modules'), ','),
            pages: self::split($string('pages'), ','),
            order: is_int($metadata['order'] ?? null) ? $metadata['order'] : 0,
            hidden: ($metadata['hidden'] ?? false) === true,
            declaresAccess: ($metadata['access'] ?? false) === true,
            unlisted: self::split(is_string($metadata['unlisted'] ?? null) ? $metadata['unlisted'] : '', ','),
        );
    }

    /**
     * @param  non-empty-string  $separator
     * @return list<string>
     */
    private static function split(string $value, string $separator): array
    {
        return $value === '' ? [] : explode($separator, $value);
    }

    /**
     * The route name namespace of the panel ("gcego.admin.").
     */
    public function routeNamespace(string $namePrefix): string
    {
        return $namePrefix.$this->routeName.'.';
    }

    public function homeRoute(string $namePrefix): string
    {
        return $this->routeNamespace($namePrefix).'home';
    }
}
