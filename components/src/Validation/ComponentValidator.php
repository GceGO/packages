<?php

declare(strict_types=1);

namespace Gcego\Components\Validation;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\TreeNode;

/**
 * Validates a component and all of its descendants against their schemas.
 *
 * validate() collects every error with its tree path; assertValid() is the
 * strict variant and throws InvalidComponentException.
 */
final readonly class ComponentValidator
{
    public function __construct(
        private PropertyValidator $propertyValidator = new PropertyValidator,
        private ChildValidator $childValidator = new ChildValidator,
        private ComponentWalker $walker = new ComponentWalker,
    ) {}

    public function validate(ComponentInterface $root): ValidationResult
    {
        $errors = [];
        $idPaths = [];

        foreach ($this->walker->preOrder($root) as $node) {
            array_push($errors, ...$this->validateNode($node));

            $id = $node->component->id();

            if ($id === null) {
                continue;
            }

            if (isset($idPaths[$id])) {
                $errors[] = ValidationError::at(
                    $node,
                    ErrorCode::DuplicateId,
                    sprintf('Duplicate component id "%s" on component "%s".', $id, ValidationError::displayName($node)),
                    subject: $id,
                    expected: 'an id unique within the tree',
                    received: sprintf('"%s", already used at %s', $id, $idPaths[$id]),
                );
            } else {
                $idPaths[$id] = $node->path->toString();
            }
        }

        return new ValidationResult(...$errors);
    }

    /**
     * @throws InvalidComponentException
     */
    public function assertValid(ComponentInterface $root): void
    {
        $this->validate($root)->throwIfInvalid();
    }

    /**
     * @return list<ValidationError>
     */
    private function validateNode(TreeNode $node): array
    {
        $definition = $node->component::definition();

        $errors = [
            ...$this->propertyValidator->validate($node, $definition),
            ...$this->childValidator->validate($node, $definition, $this->walker->childNodes($node)),
        ];

        foreach ($definition->constraints() as $constraint) {
            $messages = $constraint($node->component);

            foreach (is_iterable($messages) ? $messages : [$messages] as $message) {
                if ($message !== null) {
                    $errors[] = ValidationError::at($node, ErrorCode::ConstraintFailed, $message);
                }
            }
        }

        return $errors;
    }
}
