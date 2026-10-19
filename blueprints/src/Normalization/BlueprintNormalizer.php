<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Normalization;

use Gcego\Blueprints\Composition\ClassifiedPart;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Normalization\ComponentNormalizer;

/**
 * Deterministic, definition-level normalization of a blueprint.
 *
 *     [
 *         'blueprint' => [name, title, slug, description, icon, titleDerived, slugDerived, class, type],
 *         'policy' => [requiredRoles, allowedRoles, singleRoles],
 *         'parts' => list<[role, multiple, path, class, type]>,  // document order
 *         'roles' => [role => [multiple, paths]],                // sorted by role
 *         'component' => <gcego/components normalization of the whole tree>,
 *     ]
 *
 * Parts reference the component tree by path instead of being normalized a
 * second time. Nothing is executed: no callbacks, models, database or HTML.
 */
final readonly class BlueprintNormalizer
{
    public function __construct(
        private bool $includeSchema = false,
    ) {}

    /**
     * @return array{blueprint: array<string, mixed>, policy: array<string, mixed>, parts: list<array<string, mixed>>, roles: array<string, array{multiple: bool, paths: list<string>}>, component: array<string, mixed>}
     */
    public function normalize(BlueprintInterface $blueprint): array
    {
        $composition = $blueprint->composition();
        $roles = [];

        foreach ($composition->roles() as $role) {
            $roles[$role] = [
                'multiple' => $composition->role($role)?->multiple === true,
                'paths' => $composition->ofRole($role)->paths(),
            ];
        }

        ksort($roles);

        return [
            'blueprint' => [
                ...$blueprint->metadata()->toArray(),
                'class' => $blueprint::class,
                'type' => $blueprint->type(),
            ],
            'policy' => $blueprint::compositionPolicy()->toArray(),
            'parts' => array_map(static fn (ClassifiedPart $entry): array => $entry->toArray(), $composition->parts()->entries()),
            'roles' => $roles,
            'component' => (new ComponentNormalizer($this->includeSchema))->normalize($blueprint),
        ];
    }
}
