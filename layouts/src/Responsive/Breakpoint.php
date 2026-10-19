<?php

declare(strict_types=1);

namespace Gcego\Layouts\Responsive;

use Gcego\Layouts\Exceptions\InvalidLayoutException;

/**
 * Tailwind CSS breakpoints, mobile first: a value set at a breakpoint applies
 * from it upwards until a larger breakpoint overrides it. "default" is every
 * width (no variant).
 */
enum Breakpoint: string
{
    case Default = 'default';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case Xxl = '2xl';

    public static function parse(mixed $value, string $subject = 'Breakpoint'): self
    {
        $breakpoint = is_string($value) ? self::tryFrom($value) : null;

        return $breakpoint ?? throw InvalidLayoutException::invalid($subject, sprintf('"%s" is not a breakpoint; use one of %s.', is_scalar($value) ? (string) $value : get_debug_type($value), implode(', ', self::names())));
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $breakpoint): string => $breakpoint->value, self::cases());
    }

    /**
     * Breakpoints with a variant (every one but Default).
     *
     * @return list<self>
     */
    public static function variants(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $breakpoint): bool => $breakpoint !== self::Default));
    }

    public function order(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
