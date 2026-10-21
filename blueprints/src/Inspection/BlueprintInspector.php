<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Inspection;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Normalization\BlueprintNormalizer;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Components\Facades\Components;

/**
 * Builds BlueprintInspection snapshots. Not coupled to any console or Laravel command.
 */
final readonly class BlueprintInspector
{
    public function __construct(
        private BlueprintValidator $validator = new BlueprintValidator,
        private BlueprintNormalizer $normalizer = new BlueprintNormalizer,
    ) {}

    public function inspect(BlueprintInterface $blueprint): BlueprintInspection
    {
        $composition = $blueprint->composition();
        $policy = $blueprint::compositionPolicy();
        $metadata = $blueprint->metadata();
        $present = $composition->roles();

        $parts = [];

        foreach ($present as $role) {
            $parts[$role] = $composition->ofRole($role)->paths();
        }

        return new BlueprintInspection(
            name: $metadata->name,
            title: $metadata->title,
            slug: $metadata->slug,
            class: $blueprint::class,
            type: $blueprint->type(),
            componentCount: Components::tree($blueprint)->count(),
            parts: $parts,
            missingRequiredRoles: array_values(array_diff($policy->requiredRoles, $present)),
            missingOptionalRoles: array_values(array_diff($policy->allowedRoles ?? [], $present, $policy->requiredRoles)),
            conflicts: $composition->issues(),
            errors: $this->validator->validate($blueprint)->errors(),
            normalized: $this->normalizer->normalize($blueprint),
        );
    }
}
