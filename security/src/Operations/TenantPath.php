<?php

declare(strict_types=1);

namespace Gcego\Security\Operations;

use Gcego\Security\Exceptions\InvalidPermissionException;

/**
 * Tenant-separated storage paths for generated files (exports, reports):
 *
 *     TenantPath::for('institution:7', 'exports/subjects.csv')   // "gcego/tenants/institution/7/exports/subjects.csv"
 *     TenantPath::for(null, 'exports/platform.csv')               // "gcego/shared/exports/platform.csv"
 *
 * Relative paths cannot climb out ("..", absolute paths, NUL bytes), so one
 * tenant's files never resolve into another tenant's directory.
 */
final class TenantPath
{
    public const string Root = 'gcego';

    public static function for(?string $tenant, string $relative): string
    {
        return self::prefix($tenant).self::relative($relative);
    }

    public static function prefix(?string $tenant): string
    {
        if ($tenant === null) {
            return self::Root.'/shared/';
        }

        [$type, $key] = array_pad(explode(':', $tenant, 2), 2, '');
        $safeKey = preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key) === 1 ? $key : hash('sha256', $key);

        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $type) !== 1 || $key === '') {
            throw InvalidPermissionException::invalid('TenantPath', 'the tenant must be "type:key".');
        }

        return self::Root.'/tenants/'.$type.'/'.$safeKey.'/';
    }

    public static function contains(?string $tenant, string $path): bool
    {
        try {
            return str_starts_with($path, self::prefix($tenant)) && self::relative(substr($path, strlen(self::prefix($tenant)))) !== '';
        } catch (InvalidPermissionException) {
            return false;
        }
    }

    private static function relative(string $relative): string
    {
        $segments = explode('/', str_replace('\\', '/', $relative));

        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, "\0") || array_intersect($segments, ['..', '.', '']) !== []) {
            throw InvalidPermissionException::invalid('TenantPath', 'paths must be relative, without "." or ".." segments.');
        }

        return implode('/', $segments);
    }
}
