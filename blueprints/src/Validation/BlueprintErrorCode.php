<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

/**
 * Blueprint-specific error codes. Component schema errors keep their
 * gcego/components code, prefixed "component.".
 */
enum BlueprintErrorCode: string
{
    case InvalidRole = 'blueprint.invalid_role';
    case DuplicateSingleton = 'blueprint.duplicate_singleton';
    case RoleConflict = 'blueprint.role_conflict';
    case RoleNotAllowed = 'blueprint.role_not_allowed';
    case MissingRequiredRole = 'blueprint.missing_required_role';
}
