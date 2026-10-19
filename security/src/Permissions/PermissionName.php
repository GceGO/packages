<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Gcego\Security\Exceptions\InvalidPermissionException;

/**
 * Valid permission and ability identifiers: "subjects.view", "admin-panel.access", "institution.members.*".
 */
final class PermissionName
{
    public const string Pattern = '/^[A-Za-z][A-Za-z0-9_:\-]*(?:\.(?:[A-Za-z0-9_:\-]+|\*))*$/';

    public static function isValid(string $name): bool
    {
        return strlen($name) <= 150 && preg_match(self::Pattern, $name) === 1;
    }

    /**
     * @throws InvalidPermissionException
     */
    public static function assert(string $name, string $kind = 'permission'): string
    {
        return self::isValid($name) ? $name : throw InvalidPermissionException::invalidName($kind, $name);
    }
}
