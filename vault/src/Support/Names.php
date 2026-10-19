<?php

declare(strict_types=1);

namespace Gcego\Vault\Support;

use Gcego\Security\Permissions\PermissionName;

/**
 * Canonical identifiers.
 *
 * Permissions are dot-separated, lowercase-first segments, 2 to 6 deep:
 *   <resource>.<ability>          subjects.viewAny, subjects.forceDelete, subjects.publish
 *   <resource>.<field>.<action>   students.email.view, students.internal_notes.update
 *   panels.<panel>.access         panels.admin.access
 *   modules.<module>.access       modules.academics.access
 *   vault.<area>.<ability>        vault.roles.assign
 * Abilities use Laravel policy method names (camelCase). Names are unique
 * case-insensitively, and are also valid gcego/security permission names.
 *
 * Roles are lowercase slugs: "administrator", "head-of-department", "exam_officer".
 */
final class Names
{
    public const string PermissionPattern = '/^[a-z][A-Za-z0-9_-]*(?:\.[a-z][A-Za-z0-9_-]*){1,5}$/';

    public const string RolePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    public static function isPermission(string $name): bool
    {
        return strlen($name) <= 150 && preg_match(self::PermissionPattern, $name) === 1 && PermissionName::isValid($name);
    }

    public static function isRole(string $name): bool
    {
        return strlen($name) <= 100 && preg_match(self::RolePattern, $name) === 1;
    }

    public static function normalize(string $permission): string
    {
        return strtolower($permission);
    }

    /**
     * Whether a configured pattern ("subjects.*", "*.viewAny", "*") covers a permission name.
     */
    public static function matches(string $pattern, string $permission): bool
    {
        return $pattern === $permission || ($pattern !== '' && str_contains($pattern, '*') && fnmatch($pattern, $permission, FNM_NOESCAPE));
    }

    /**
     * A readable label from an identifier: "forceDelete" -> "Force delete", "internal_notes" -> "Internal notes".
     */
    public static function label(string $identifier): string
    {
        $words = strtolower(trim((string) preg_replace(['/([a-z0-9])([A-Z])/', '/[-_.]+/'], ['$1 $2', ' '], $identifier)));

        return ucfirst($words);
    }
}
