<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Support;

use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use Illuminate\Contracts\Container\Container;

/**
 * The single place Luminaries reads Blade Icons set registrations.
 *
 * Blade Icons exposes registered sets only through Factory::all() (marked
 * internal), so every use of it is isolated here. The factory is resolved
 * lazily: booting Luminaries never forces Blade Icons to boot.
 */
final class BladeIconSets
{
    private ?Factory $factory = null;

    public function __construct(private readonly Container $container) {}

    public function factory(): Factory
    {
        return $this->factory ??= $this->container->make(Factory::class);
    }

    public function has(string $set): bool
    {
        return $this->set($set) !== null;
    }

    public function prefix(string $set): ?string
    {
        $prefix = $this->set($set)['prefix'] ?? null;

        return is_string($prefix) ? $prefix : null;
    }

    public function usesDisk(string $set): bool
    {
        return ($this->set($set)['disk'] ?? null) !== null;
    }

    /**
     * @return list<string>
     */
    public function paths(string $set): array
    {
        $paths = $this->set($set)['paths'] ?? [];

        return array_values(array_filter(is_array($paths) ? $paths : [], is_string(...)));
    }

    /**
     * Every icon file name in a set (Blade Icons manifest: cached by icons:cache, otherwise scanned).
     *
     * @return list<string>
     */
    public function names(string $set): array
    {
        $manifest = $this->container->make(IconsManifest::class)->getManifest($this->factory()->all());
        $names = [];

        foreach ((array) ($manifest[$set] ?? []) as $icons) {
            foreach ((array) $icons as $icon) {
                if (is_string($icon)) {
                    $names[] = $icon;
                }
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function set(string $set): ?array
    {
        $options = $this->factory()->all()[$set] ?? null;

        return is_array($options) ? $options : null;
    }
}
