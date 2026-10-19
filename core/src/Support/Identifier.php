<?php

declare(strict_types=1);

namespace Gcego\Core\Support;

/**
 * Validation of the identifiers used for packages, extensions and capabilities.
 *
 * @internal
 */
final class Identifier
{
    /**
     * Composer package names: "vendor/package".
     */
    public const string PackagePattern = '#^[a-z0-9](?:[_.-]?[a-z0-9]+)*/[a-z0-9](?:(?:[_.]|-{1,2})?[a-z0-9]+)*$#';

    /**
     * Lowercase dotted names: "tables.export", "security.authorization".
     */
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    public static function isPackage(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 128 && preg_match(self::PackagePattern, $value) === 1;
    }

    public static function isName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 128 && preg_match(self::NamePattern, $value) === 1;
    }
}
