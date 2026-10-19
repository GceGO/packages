<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Providers;

use BladeUI\Icons\Exceptions\SvgNotFound;
use Gcego\Luminaries\Contracts\IconProvider;
use Gcego\Luminaries\Exceptions\IconNotFoundException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Support\BladeIconSets;
use Gcego\Luminaries\Support\ProviderMetadata;

/**
 * Exposes one Blade Icons set, or one variant of it, as a Luminaries provider.
 *
 * Heroicons ships its variants as file prefixes inside one set (o-, s-, m-,
 * c-), so "heroicon-solid:academic-cap" maps to the Blade Icons name
 * "heroicon-s-academic-cap". Existence is a cached file check; SVG contents
 * come from the Blade Icons factory (and its in-memory cache).
 */
final class BladeIconSetProvider implements IconProvider
{
    private const int MemoLimit = 4096;

    /**
     * @var array<string, bool>
     */
    private array $exists = [];

    public function __construct(
        private readonly BladeIconSets $sets,
        private readonly string $name,
        private readonly string $label,
        private readonly string $set,
        private readonly ?string $variant = null,
        private readonly string $style = 'outline',
        private readonly bool $strokeWidth = false,
        private readonly ?string $source = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function set(): string
    {
        return $this->set;
    }

    public function available(): bool
    {
        return $this->sets->has($this->set);
    }

    public function has(string $icon): bool
    {
        if (isset($this->exists[$icon])) {
            return $this->exists[$icon];
        }

        if (count($this->exists) >= self::MemoLimit) {
            $this->exists = [];
        }

        return $this->exists[$icon] = $this->lookup($icon);
    }

    public function contents(string $icon): string
    {
        $native = $this->has($icon) ? $this->nativeName($icon) : null;

        if ($native === null) {
            throw $this->missing($icon);
        }

        try {
            return $this->sets->factory()->svg($native)->contents();
        } catch (SvgNotFound) {
            throw $this->missing($icon);
        }
    }

    public function nativeName(string $icon): ?string
    {
        $prefix = $this->available() ? $this->sets->prefix($this->set) : null;

        return $prefix === null ? null : $prefix.'-'.$this->file($icon);
    }

    public function icons(): array
    {
        if (! $this->available()) {
            return [];
        }

        $icons = [];
        $prefix = $this->variant === null ? '' : $this->variant.'-';

        foreach ($this->sets->names($this->set) as $file) {
            if ($prefix !== '' && ! str_starts_with($file, $prefix)) {
                continue;
            }

            $icon = substr($file, strlen($prefix));

            if (preg_match(IconName::IconPattern, $icon) === 1) {
                $icons[] = $icon;
            }
        }

        return $icons;
    }

    public function metadata(): ProviderMetadata
    {
        $paths = $this->available() ? $this->sets->paths($this->set) : [];

        return new ProviderMetadata($this->name, $this->label, 'blade-icons', $this->style, $this->strokeWidth, $this->source ?? 'blade-icons set "'.$this->set.'"', $paths[0] ?? null);
    }

    private function lookup(string $icon): bool
    {
        if (preg_match(IconName::IconPattern, $icon) !== 1 || ! $this->available()) {
            return false;
        }

        if ($this->sets->usesDisk($this->set)) {
            $native = $this->nativeName($icon);

            if ($native === null) {
                return false;
            }

            try {
                $this->sets->factory()->svg($native);

                return true;
            } catch (SvgNotFound) {
                return false;
            }
        }

        foreach ($this->sets->paths($this->set) as $path) {
            if (is_file($path.'/'.$this->file($icon).'.svg')) {
                return true;
            }
        }

        return false;
    }

    private function file(string $icon): string
    {
        return $this->variant === null ? $icon : $this->variant.'-'.$icon;
    }

    private function missing(string $icon): IconNotFoundException
    {
        return new IconNotFoundException(
            IconName::canonical($this->name, $icon),
            $this->name,
            null,
            $this->available()
                ? sprintf('The icon is not available in the installed "%s" collection%s.', $this->set, $this->variant === null ? '' : sprintf(' (variant "%s")', $this->variant))
                : sprintf('The Blade Icons set "%s" is not registered; install its package or disable the provider.', $this->set),
        );
    }
}
