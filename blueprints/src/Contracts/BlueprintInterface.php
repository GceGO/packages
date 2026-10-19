<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

use Gcego\Blueprints\Composition\BlueprintComposition;
use Gcego\Blueprints\Composition\BlueprintPartCollection;
use Gcego\Blueprints\Composition\CompositionPolicy;
use Gcego\Blueprints\Exceptions\BlueprintCompositionException;
use Gcego\Blueprints\Exceptions\DuplicateBlueprintPartException;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Exceptions\MissingBlueprintPartException;
use Gcego\Blueprints\Metadata\BlueprintMetadata;
use Gcego\Blueprints\Support\BlueprintIdentity;
use Gcego\Components\Contracts\ComponentInterface;

/**
 * The read-only contract registries and runtimes consume.
 */
interface BlueprintInterface extends ComponentInterface
{
    public static function compositionPolicy(): CompositionPolicy;

    public function name(): ?string;

    public function metadata(): BlueprintMetadata;

    /**
     * @throws InvalidBlueprintException when the blueprint has no valid name.
     */
    public function identity(): BlueprintIdentity;

    public function composition(): BlueprintComposition;

    /**
     * The part of a single-cardinality role, or null when absent.
     *
     * @throws BlueprintCompositionException when the role allows several parts.
     * @throws DuplicateBlueprintPartException when several parts claim a single role.
     */
    public function part(string $role): ?BlueprintPart;

    /**
     * Like part(), but a missing part throws.
     *
     * @throws MissingBlueprintPartException
     * @throws BlueprintCompositionException when the role allows several parts.
     * @throws DuplicateBlueprintPartException when several parts claim a single role.
     */
    public function requirePart(string $role): BlueprintPart;

    /**
     * All parts, or the parts of one role, in document order.
     */
    public function parts(?string $role = null): BlueprintPartCollection;

    public function hasPart(string $role): bool;

    /**
     * @return list<string>
     */
    public function roles(): array;
}
