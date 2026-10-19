<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Composition;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Blueprints\Contracts\PartContainer;
use Gcego\Blueprints\Exceptions\InvalidBlueprintPartException;
use Gcego\Blueprints\Validation\BlueprintErrorCode;
use Gcego\Blueprints\Validation\BlueprintValidationError;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Traversal\Visit;

/**
 * Classifies a blueprint's parts by role and applies the cardinality rules.
 *
 * It adds blueprint semantics on top of gcego/components traversal; it never
 * builds its own tree. Parts are the blueprint's direct BlueprintPart
 * children, plus the BlueprintPart children of PartContainer parts.
 *
 * Cardinality rules, per role:
 *  - every part of a role must agree on BlueprintRole::$multiple (else RoleConflict);
 *  - a single role (declared by its parts or forced by the policy) allows one part (else DuplicateSingleton);
 *  - the policy may disallow roles (RoleNotAllowed) or require them (MissingRequiredRole).
 */
final readonly class BlueprintComposer
{
    public function __construct(
        private ComponentWalker $walker = new ComponentWalker,
    ) {}

    public function compose(BlueprintInterface $blueprint): BlueprintComposition
    {
        $name = $blueprint->name() ?? $blueprint::class;
        [$entries, $issues] = $this->classify($blueprint, $name);

        /** @var array<string, list<ClassifiedPart>> $grouped */
        $grouped = [];

        foreach ($entries as $entry) {
            $grouped[$entry->role->name][] = $entry;
        }

        $policy = $blueprint::compositionPolicy();
        $roles = [];
        $byRole = [];

        foreach ($grouped as $role => $roleEntries) {
            array_push($issues, ...$this->checkRole($name, $role, $roleEntries, $policy));

            $declaredMultiple = array_values(array_unique(array_map(static fn (ClassifiedPart $entry): bool => $entry->role->multiple, $roleEntries)));
            $multiple = $declaredMultiple === [true] && ! $policy->forcesSingle($role);

            $roles[$role] = new BlueprintRole($role, $multiple);
            $byRole[$role] = new BlueprintPartCollection(...$roleEntries);
        }

        $rootPath = $this->walker->rootNode($blueprint)->path->toString();

        foreach ($policy->requiredRoles as $required) {
            if (! isset($grouped[$required])) {
                $issues[] = new BlueprintValidationError(
                    BlueprintErrorCode::MissingRequiredRole->value,
                    sprintf('Blueprint "%s" requires a "%s" part.', $name, $required),
                    $name,
                    $rootPath,
                    $required,
                    expected: sprintf('at least one "%s" part', $required),
                    received: 'none',
                );
            }
        }

        return new BlueprintComposition(new BlueprintPartCollection(...$entries), $byRole, $roles, $issues);
    }

    /**
     * @return array{0: list<ClassifiedPart>, 1: list<BlueprintValidationError>}
     */
    private function classify(BlueprintInterface $blueprint, string $name): array
    {
        $entries = [];
        $issues = [];
        $generator = $this->walker->preOrder($blueprint);

        while ($generator->valid()) {
            $node = $generator->current();
            $component = $node->component;

            if ($node->isRoot()) {
                $generator->next();

                continue;
            }

            if (! $component instanceof BlueprintPart) {
                // Direct non-part children are reported by the component schema (allowed children).
                $generator->send(Visit::SkipChildren);

                continue;
            }

            try {
                $entries[] = new ClassifiedPart($component, $component->blueprintRole(), $node->path);
            } catch (InvalidBlueprintPartException $exception) {
                $issues[] = new BlueprintValidationError(
                    BlueprintErrorCode::InvalidRole->value,
                    sprintf('Part %s in blueprint "%s" declares an invalid role.', $component::class, $name),
                    $name,
                    $node->path->toString(),
                    expected: 'a role identifier such as "table"',
                    hint: $exception->getMessage(),
                );
            }

            $generator->send($component instanceof PartContainer ? Visit::Continue : Visit::SkipChildren);
        }

        return [$entries, $issues];
    }

    /**
     * @param  list<ClassifiedPart>  $entries
     * @return list<BlueprintValidationError>
     */
    private function checkRole(string $name, string $role, array $entries, CompositionPolicy $policy): array
    {
        $issues = [];

        if (! $policy->isAllowed($role)) {
            foreach ($entries as $entry) {
                $issues[] = new BlueprintValidationError(
                    BlueprintErrorCode::RoleNotAllowed->value,
                    sprintf('Blueprint "%s" does not accept "%s" parts.', $name, $role),
                    $name,
                    $entry->path->toString(),
                    $role,
                    expected: 'one of the roles '.implode(', ', $policy->allowedRoles ?? []),
                    received: $entry->part::class,
                );
            }

            return $issues;
        }

        $multiples = array_unique(array_map(static fn (ClassifiedPart $entry): bool => $entry->role->multiple, $entries));

        if (count($multiples) > 1) {
            return [new BlueprintValidationError(
                BlueprintErrorCode::RoleConflict->value,
                sprintf('Parts of blueprint "%s" disagree about whether role "%s" allows several parts.', $name, $role),
                $name,
                $entries[0]->path->toString(),
                $role,
                expected: 'every part of the role to declare the same cardinality',
                received: implode(', ', array_map(
                    static fn (ClassifiedPart $entry): string => sprintf('%s (%s)', $entry->path->toString(), $entry->role->multiple ? 'multiple' : 'single'),
                    $entries,
                )),
            )];
        }

        $single = ! $entries[0]->role->multiple || $policy->forcesSingle($role);

        if ($single) {
            foreach (array_slice($entries, 1) as $duplicate) {
                $issues[] = new BlueprintValidationError(
                    BlueprintErrorCode::DuplicateSingleton->value,
                    sprintf('Blueprint "%s" already has a "%s" part at %s; the role allows only one.', $name, $role, $entries[0]->path->toString()),
                    $name,
                    $duplicate->path->toString(),
                    $role,
                    expected: sprintf('one "%s" part', $role),
                    received: sprintf('%d "%s" parts', count($entries), $role),
                );
            }
        }

        return $issues;
    }
}
