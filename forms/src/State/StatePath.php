<?php

declare(strict_types=1);

namespace Gcego\Forms\State;

use Gcego\Forms\Exceptions\InvalidFieldException;

/**
 * State path rules. Field names are identifiers; repeater items are keyed by
 * stable random keys ("i3f9c2a10b7"), never by position, so reordering or
 * removing items never shifts another item's values or errors.
 */
final class StatePath
{
    public const string NamePattern = '/^[a-z_][a-z0-9_]{0,63}$/i';

    public const string ItemKeyPattern = '/^i[a-f0-9]{10}$/';

    public static function assertName(string $name): string
    {
        return preg_match(self::NamePattern, $name) === 1 ? $name : throw InvalidFieldException::invalid('Field "'.mb_substr($name, 0, 64).'"', 'names are identifiers (letters, digits and "_").');
    }

    public static function newItemKey(): string
    {
        return 'i'.bin2hex(random_bytes(5));
    }

    public static function isItemKey(mixed $key): bool
    {
        return is_string($key) && preg_match(self::ItemKeyPattern, $key) === 1;
    }

    public static function join(?string $parent, string $segment): string
    {
        return $parent === null || $parent === '' ? $segment : $parent.'.'.$segment;
    }
}
