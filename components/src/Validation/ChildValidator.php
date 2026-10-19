<?php

declare(strict_types=1);

namespace Gcego\Components\Validation;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\TreeNode;

/**
 * Validates the children and configuration nodes of one component occurrence.
 */
final class ChildValidator
{
    /**
     * @param  list<TreeNode>  $children  The occurrence's child nodes, in order.
     * @return list<ValidationError>
     */
    public function validate(TreeNode $node, ComponentDefinition $definition, array $children): array
    {
        $errors = [];
        $name = ValidationError::displayName($node);
        $count = count($children);

        if (! $definition->allowsChildren && $count > 0) {
            $errors[] = ValidationError::at(
                $node,
                ErrorCode::ChildrenNotAllowed,
                sprintf('Component "%s" does not accept child components.', $name),
                expected: 'no children',
                received: sprintf('%d child component(s)', $count),
            );
        } elseif ($definition->allowsChildren) {
            foreach ($children as $child) {
                if (! $definition->allowsChild($child->component)) {
                    $errors[] = ValidationError::at(
                        $node,
                        ErrorCode::InvalidChild,
                        sprintf('Component "%s" does not accept a child of type "%s".', $name, $child->component->type()),
                        subject: $child->component::class,
                        expected: 'one of '.implode(', ', $definition->allowedChildren ?? []),
                        received: $child->component::class,
                        path: $child->path->toString(),
                    );
                }
            }

            if ($count < $definition->minChildren) {
                $errors[] = ValidationError::at(
                    $node,
                    ErrorCode::TooFewChildren,
                    sprintf('Component "%s" has too few children.', $name),
                    expected: sprintf('at least %d child component(s)', $definition->minChildren),
                    received: sprintf('%d child component(s)', $count),
                );
            }

            if ($definition->maxChildren !== null && $count > $definition->maxChildren) {
                $errors[] = ValidationError::at(
                    $node,
                    ErrorCode::TooManyChildren,
                    sprintf('Component "%s" has too many children.', $name),
                    expected: sprintf('at most %d child component(s)', $definition->maxChildren),
                    received: sprintf('%d child component(s)', $count),
                );
            }
        }

        foreach ($node->component->configurations() as $configuration) {
            if (! $definition->allowsConfiguration($configuration)) {
                $errors[] = ValidationError::at(
                    $node,
                    ErrorCode::InvalidConfiguration,
                    sprintf('Component "%s" does not accept configuration "%s".', $name, $configuration::class),
                    subject: $configuration::class,
                    expected: $definition->allowedConfigurations === [] ? 'no configuration nodes' : 'one of '.implode(', ', $definition->allowedConfigurations ?? []),
                    received: $configuration::class,
                );
            }
        }

        return $errors;
    }
}
