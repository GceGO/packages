<?php

declare(strict_types=1);

namespace Gcego\Components\Validation;

/**
 * Stable machine-readable validation error identifiers.
 *
 * Duplicate properties and unsupported constructor arguments are not listed:
 * they are structural and rejected with an exception at construction time.
 */
enum ErrorCode: string
{
    case MissingProperty = 'missing_property';
    case InvalidType = 'invalid_type';
    case InvalidValue = 'invalid_value';
    case UnknownProperty = 'unknown_property';
    case ChildrenNotAllowed = 'children_not_allowed';
    case InvalidChild = 'invalid_child';
    case TooFewChildren = 'too_few_children';
    case TooManyChildren = 'too_many_children';
    case InvalidConfiguration = 'invalid_configuration';
    case DuplicateId = 'duplicate_id';
    case ConstraintFailed = 'constraint_failed';
}
