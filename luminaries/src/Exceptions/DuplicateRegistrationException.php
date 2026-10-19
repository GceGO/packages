<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Exceptions;

/**
 * A provider, alias or inline icon was registered twice without an explicit override.
 */
final class DuplicateRegistrationException extends LuminariesException
{
    public static function provider(string $name): self
    {
        return new self(self::header().sprintf('An icon provider named "%s" is already registered.', $name));
    }

    public static function alias(string $alias, string $existing): self
    {
        return new self(self::header().sprintf('The alias "%s" is already mapped to "%s"; pass override: true to replace it.', $alias, $existing));
    }

    public static function icon(string $name): self
    {
        return new self(self::header().sprintf('The inline icon "%s" is already registered; pass override: true to replace it.', $name));
    }
}
