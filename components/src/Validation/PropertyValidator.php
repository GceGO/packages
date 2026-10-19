<?php

declare(strict_types=1);

namespace Gcego\Components\Validation;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Support\ValueDescriber;
use Gcego\Components\TreeNode;

/**
 * Validates the properties of one component occurrence against its definition.
 *
 * Each property reports at most one error: the first failing check out of
 * presence, type, allowed values and custom rule.
 */
final class PropertyValidator
{
    private const int MaxSuggestionDistance = 2;

    /**
     * @return list<ValidationError>
     */
    public function validate(TreeNode $node, ComponentDefinition $definition): array
    {
        $errors = [];
        $properties = $node->component->properties();

        if (! $definition->allowsUnknownProperties) {
            foreach ($properties->keys() as $key) {
                if (! $definition->hasProperty($key)) {
                    $errors[] = $this->unknown($node, $definition, $key);
                }
            }
        }

        foreach ($definition->properties() as $name => $propertyDefinition) {
            $property = $properties->get($name);
            $error = $property === null
                ? $this->validateAbsent($node, $propertyDefinition)
                : $this->validateValue($node, $propertyDefinition, $property->value);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    public function validateValue(TreeNode $node, PropertyDefinition $definition, mixed $value): ?ValidationError
    {
        [$code, $expected] = match (true) {
            ! $definition->acceptsType($value) => [ErrorCode::InvalidType, $definition->expectedType()],
            $value === null => [null, null],
            ! $definition->allowsValue($value) => [ErrorCode::InvalidValue, $definition->expectedValues()],
            ! $definition->satisfiesRule($value) => [ErrorCode::InvalidValue, $definition->expectedRule()],
            default => [null, null],
        };

        if ($code === null) {
            return null;
        }

        return ValidationError::at(
            $node,
            $code,
            sprintf('Invalid property "%s" on component "%s".', $definition->name, ValidationError::displayName($node)),
            subject: $definition->name,
            expected: $expected,
            received: ValueDescriber::describe($value),
        );
    }

    private function validateAbsent(TreeNode $node, PropertyDefinition $definition): ?ValidationError
    {
        if (! $definition->required) {
            return null;
        }

        return ValidationError::at(
            $node,
            ErrorCode::MissingProperty,
            sprintf('Missing required property "%s" on component "%s".', $definition->name, ValidationError::displayName($node)),
            subject: $definition->name,
            expected: $definition->expectedType(),
            received: 'nothing',
        );
    }

    private function unknown(TreeNode $node, ComponentDefinition $definition, string $key): ValidationError
    {
        $suggestion = $this->closestName($key, $definition->propertyNames());
        $supported = $definition->propertyNames();

        return ValidationError::at(
            $node,
            ErrorCode::UnknownProperty,
            sprintf('Unknown property "%s" on component "%s".', $key, ValidationError::displayName($node)),
            subject: $key,
            expected: $supported === [] ? 'no properties' : 'one of '.implode(', ', $supported),
            received: sprintf('"%s"', $key),
            hint: $suggestion === null ? null : sprintf('Did you mean "%s"?', $suggestion),
        );
    }

    /**
     * @param  list<string>  $candidates
     */
    private function closestName(string $key, array $candidates): ?string
    {
        $best = null;
        $bestDistance = self::MaxSuggestionDistance + 1;

        foreach ($candidates as $candidate) {
            $distance = levenshtein(strtolower($key), strtolower($candidate));

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
