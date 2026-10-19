<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tokens;

/**
 * One semantic color token in the catalog.
 *
 * Its custom property is --gcego-<name>. Tailwind utilities: bg-<name> (canonical)
 * and, for the original gcego/chroma colors only, bg-gcego-<name>.
 */
final readonly class Token
{
    public function __construct(
        public string $name,
        public TokenGroup $group,
        public string $light,
        public ?string $dark,
        public string $description,
    ) {}

    public static function property(string $name): string
    {
        return '--gcego-'.$name;
    }

    /**
     * Whether Tailwind gets an unprefixed utility name (bg-surface). Legacy aliases
     * such as "text" only keep their gcego-* spelling (text-gcego-text).
     */
    public function hasCanonicalUtility(): bool
    {
        return $this->group !== TokenGroup::Legacy;
    }

    /**
     * Whether Tailwind also gets the original gcego-* spelling (bg-gcego-surface).
     */
    public function hasPrefixedUtility(): bool
    {
        return in_array($this->name, SemanticTokens::GcegoPrefixed, true);
    }

    public function value(bool $dark): string
    {
        return $dark ? ($this->dark ?? $this->light) : $this->light;
    }
}
