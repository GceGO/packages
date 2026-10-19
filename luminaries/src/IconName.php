<?php

declare(strict_types=1);

namespace Gcego\Luminaries;

use BackedEnum;
use Gcego\Luminaries\Exceptions\InvalidIconNameException;

/**
 * A parsed icon identifier. Three forms are accepted:
 *
 * - Canonical: "provider:icon" (lucide:graduation-cap, heroicon-solid:academic-cap, custom:gcego-logo).
 * - Semantic alias: dotted segments (actions.edit, navigation.dashboard).
 * - Bare: a single name (pencil-square), resolved through inline icons and then the default provider.
 *
 * Only lowercase letters, digits, dashes, one colon (canonical) or dots
 * (aliases) are allowed, so identifiers can never carry path separators,
 * markup or attribute syntax into the filesystem or the rendered SVG.
 */
final readonly class IconName
{
    public const string Canonical = 'canonical';

    public const string Alias = 'alias';

    public const string Bare = 'bare';

    /**
     * Any identifier accepted by Luminaries; use it to validate icon properties.
     */
    public const string Pattern = '/^(?:[a-z][a-z0-9-]{0,31}:[a-z0-9][a-z0-9-]{0,63}|[a-z][a-z0-9-]{0,31}(?:\.[a-z0-9][a-z0-9-]{0,31}){1,3}|[a-z][a-z0-9-]{0,63})$/';

    public const string ProviderPattern = '/^[a-z][a-z0-9-]{0,31}$/';

    public const string IconPattern = '/^[a-z0-9][a-z0-9-]{0,63}$/';

    public const string AliasPattern = '/^[a-z][a-z0-9-]{0,31}(?:\.[a-z0-9][a-z0-9-]{0,31}){1,3}$/';

    private function __construct(
        public string $value,
        public string $kind,
        public ?string $provider,
        public string $icon,
    ) {}

    /**
     * @throws InvalidIconNameException
     */
    public static function parse(string $name): self
    {
        if (preg_match(self::Pattern, $name) !== 1) {
            throw InvalidIconNameException::for($name);
        }

        if (str_contains($name, ':')) {
            [$provider, $icon] = explode(':', $name, 2);

            return new self($name, self::Canonical, $provider, $icon);
        }

        if (str_contains($name, '.')) {
            return new self($name, self::Alias, null, $name);
        }

        return new self($name, self::Bare, null, $name);
    }

    /**
     * The identifier of a name or a typed reference (Icons\Heroicon::Home -> "heroicon:home").
     * Accepts any string-backed enum, so application enums of aliases work too.
     *
     * @throws InvalidIconNameException for an int-backed enum
     */
    public static function of(string|BackedEnum $name): string
    {
        if (! $name instanceof BackedEnum) {
            return $name;
        }

        if (! is_string($name->value)) {
            throw InvalidIconNameException::for($name::class.'::'.$name->name);
        }

        return $name->value;
    }

    /**
     * The identifier of a valid name or string-backed enum case, else null: for
     * data-driven values (maps, payloads) that must never throw.
     */
    public static function tryOf(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return is_string($value) && self::isValid($value) ? $value : null;
    }

    /**
     * Whether a value is an icon name or a typed reference to one: the rule of
     * every icon property ("rule: IconName::accepts(...)").
     */
    public static function accepts(mixed $value): bool
    {
        return self::tryOf($value) !== null;
    }

    public static function isValid(string $name): bool
    {
        return preg_match(self::Pattern, $name) === 1;
    }

    public static function canonical(string $provider, string $icon): string
    {
        return $provider.':'.$icon;
    }

    public function isAlias(): bool
    {
        return $this->kind === self::Alias;
    }

    public function isCanonical(): bool
    {
        return $this->kind === self::Canonical;
    }

    public function isBare(): bool
    {
        return $this->kind === self::Bare;
    }
}
