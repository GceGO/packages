<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

/**
 * An integration check on one class reference, contributed by the package
 * that understands the referenced capability (an adapter).
 *
 * gcego/blueprints only knows reference structure. A feature package that is
 * installed, such as gcego/tables, adds a rule requiring that "table"
 * references implement its contract; when it is not installed, its
 * references are stored and reported, but never interpreted.
 */
interface ReferenceRule
{
    /**
     * Whether the rule checks references of this definition key.
     */
    public function appliesTo(string $key): bool;

    /**
     * Null when the reference is acceptable, else what is wrong with it.
     *
     * @param  class-string  $class
     */
    public function check(string $key, string $class): ?string;

    /**
     * The error code reported for a failed check, such as "blueprint.incompatible_reference".
     */
    public function code(): string;
}
