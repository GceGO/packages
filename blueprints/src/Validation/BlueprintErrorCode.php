<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

/**
 * Error codes of gcego/blueprints.
 *
 * Structural codes come from Blueprint::define(); reference codes come from
 * the BlueprintValidator when the registry resolves a blueprint. Adapters
 * may report their own "<package>.<code>" codes.
 */
enum BlueprintErrorCode: string
{
    case UnsupportedDefinition = 'blueprint.unsupported_definition';
    case DuplicateDefinition = 'blueprint.duplicate_definition';
    case MissingRequiredDefinition = 'blueprint.missing_required_definition';
    case UnresolvableReference = 'blueprint.unresolvable_reference';
    case IncompatibleReference = 'blueprint.incompatible_reference';
}
