<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Components\Validation\ErrorCode;

/**
 * Validates a blueprint with full structure: component schema errors from
 * gcego/morfaw (metadata, children, nested parts) plus role-aware
 * composition errors.
 *
 * Blueprint::validate() and assertValid() already reject the same
 * blueprints (composition rules are part of the component schema as a
 * constraint); this validator adds codes, roles and paths to those errors.
 */
final readonly class BlueprintValidator
{
    public function __construct(
        private ?ComponentValidator $components = null,
        private ?ComponentWalker $walker = null,
    ) {}

    public function validate(BlueprintInterface $blueprint): BlueprintValidationResult
    {
        $name = $blueprint->name() ?? $blueprint::class;
        $composition = $blueprint->composition();
        $compositionMessages = array_flip($composition->messages());
        $rootPath = ($this->walker ?? app(ComponentWalker::class))->rootNode($blueprint)->path->toString();
        $errors = [];

        foreach (($this->components ?? app(ComponentValidator::class))->validate($blueprint) as $error) {
            // The composition constraint reports the same issues as plain messages; keep only the structured form.
            if ($error->code === ErrorCode::ConstraintFailed && $error->path === $rootPath && isset($compositionMessages[$error->message])) {
                continue;
            }

            $errors[] = BlueprintValidationError::fromComponentError($name, $error);
        }

        return new BlueprintValidationResult($name, ...$errors, ...$composition->issues());
    }

    /**
     * @throws InvalidBlueprintException
     */
    public function assertValid(BlueprintInterface $blueprint): void
    {
        $this->validate($blueprint)->throwIfInvalid();
    }
}
