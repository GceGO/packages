<?php

declare(strict_types=1);

namespace Gcego\Vault\Enums;

/**
 * Every change Vault records in its audit log.
 */
enum AuditEvent: string
{
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDuplicated = 'role.duplicated';
    case RoleArchived = 'role.archived';
    case RoleRestored = 'role.restored';
    case RoleDeleted = 'role.deleted';
    case RolePermissionsChanged = 'role.permissions_changed';
    case RoleInheritanceChanged = 'role.inheritance_changed';
    case RolesImported = 'roles.imported';
    case PermissionCreated = 'permission.created';
    case PermissionUpdated = 'permission.updated';
    case PermissionDeleted = 'permission.deleted';
    case PermissionsSynced = 'permissions.synced';
    case RoleAssigned = 'role.assigned';
    case RoleRevoked = 'role.revoked';
    case PermissionGranted = 'permission.granted';
    case PermissionRevoked = 'permission.revoked';
    case AccessExpired = 'access.expired';
    case RequestSubmitted = 'request.submitted';
    case RequestReviewed = 'request.reviewed';
    case RequestApproved = 'request.approved';
    case RequestRejected = 'request.rejected';
    case RequestCancelled = 'request.cancelled';
    case EscalationBlocked = 'escalation.blocked';
    case CacheCleared = 'cache.cleared';

    public function label(): string
    {
        return (string) __('gcego-vault::messages.events.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::RoleDeleted, self::PermissionDeleted, self::RoleRevoked, self::PermissionRevoked, self::RequestRejected, self::EscalationBlocked => 'danger',
            self::RoleArchived, self::AccessExpired, self::RequestCancelled => 'warning',
            self::RoleAssigned, self::PermissionGranted, self::RequestApproved, self::RoleCreated, self::PermissionCreated => 'success',
            default => 'info',
        };
    }
}
