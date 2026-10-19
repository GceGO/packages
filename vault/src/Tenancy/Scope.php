<?php

declare(strict_types=1);

namespace Gcego\Vault\Tenancy;

use Gcego\Security\Tenancy\Tenant;

/**
 * Where an assignment applies, as stored: "" (global, platform-wide) or a
 * gcego/security tenant id ("institution:12"). Global and tenant scopes never
 * mix: a global role grants nothing inside a tenant, and a tenant role grants
 * nothing globally or in another tenant.
 */
final class Scope
{
    public const string Global = '';

    public static function of(?Tenant $tenant): string
    {
        return $tenant?->id() ?? self::Global;
    }

    public static function isValid(string $scope): bool
    {
        return $scope === self::Global || (strlen($scope) <= 100 && preg_match('/^[a-z][a-z0-9_.-]{0,63}:[A-Za-z0-9_.:-]{1,35}$/', $scope) === 1);
    }

    public static function label(string $scope): string
    {
        return $scope === self::Global ? (string) __('gcego-vault::messages.scope.global') : $scope;
    }
}
