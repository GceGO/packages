<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

/**
 * Why a decision denied. Codes are safe to log and to show to developers;
 * the public message never names users, tenants or records.
 */
enum DenialReason: string
{
    case Unauthenticated = 'unauthenticated';
    case WrongGuard = 'wrong_guard';
    case MissingPermission = 'missing_permission';
    case UnknownPermission = 'unknown_permission';
    case PolicyDenied = 'policy_denied';
    case NoPolicy = 'no_policy';
    case NoRequirement = 'no_requirement';
    case TenantRequired = 'tenant_required';
    case TenantDenied = 'tenant_denied';
    case NotFound = 'not_found';
    case EmailNotVerified = 'email_not_verified';
    case PasswordNotConfirmed = 'password_not_confirmed';
    case RuleDenied = 'rule_denied';
    case Stale = 'stale';

    public function describe(): string
    {
        return match ($this) {
            self::Unauthenticated => 'No user is authenticated.',
            self::WrongGuard => 'The user is not authenticated on the required guard.',
            self::MissingPermission => 'The user lacks a required permission.',
            self::UnknownPermission => 'The permission is not known to the permission provider.',
            self::PolicyDenied => 'The policy or ability denied the request.',
            self::NoPolicy => 'No policy or ability defines this check, so it is denied.',
            self::NoRequirement => 'No authorization requirement is declared, so access is denied.',
            self::TenantRequired => 'No tenant is selected.',
            self::TenantDenied => 'The user has no verified membership in the tenant.',
            self::NotFound => 'The resource does not exist within the permitted scope.',
            self::EmailNotVerified => 'The email address is not verified.',
            self::PasswordNotConfirmed => 'The password was not confirmed recently.',
            self::RuleDenied => 'An authorization rule denied the request.',
            self::Stale => 'The authorization no longer holds.',
        };
    }
}
