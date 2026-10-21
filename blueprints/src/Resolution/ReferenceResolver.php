<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Resolution;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Exceptions\InvalidReferenceException;
use Gcego\Blueprints\Validation\BlueprintValidationResult;
use Gcego\Blueprints\Validation\BlueprintValidator;
use WeakMap;

/**
 * Checked access to a blueprint's class references for the engines that
 * execute them (instantiate, call statically, render).
 *
 * It always applies the full reference rules, whatever
 * gcego-blueprints.validate_references says, and whether or not the
 * blueprint came through the registry. Engines take references from here
 * rather than from $blueprint->table() before running them:
 *
 *     $table = app(ReferenceResolver::class)->one($blueprint, Table::Key, TableDefinition::class);
 *
 * Results are memoized per blueprint object (blueprints are immutable).
 */
final class ReferenceResolver
{
    /**
     * @var WeakMap<BlueprintInterface, BlueprintValidationResult>
     */
    private WeakMap $checked;

    public function __construct(
        private readonly BlueprintValidator $validator,
    ) {
        $this->checked = new WeakMap;
    }

    /**
     * The checked references of one definition key, in declaration order.
     *
     * @template T of object
     *
     * @param  class-string<T>|null  $contract  Additionally require every reference to implement this.
     * @return ($contract is null ? list<class-string> : list<class-string<T>>)
     *
     * @throws InvalidReferenceException
     */
    public function all(BlueprintInterface $blueprint, string $key, ?string $contract = null): array
    {
        $result = $this->checked[$blueprint] ??= $this->validator->validate($blueprint);
        $errors = $result->errorsForKey($key);

        if ($errors !== []) {
            throw InvalidReferenceException::forKey($blueprint->name(), $key, new BlueprintValidationResult($blueprint->name(), ...$errors));
        }

        $classes = $blueprint->references()[$key] ?? [];

        if ($contract === null) {
            return $classes;
        }

        $checked = [];

        foreach ($classes as $class) {
            if (! is_a($class, $contract, true)) {
                throw InvalidReferenceException::contract($blueprint->name(), $key, $class, $contract);
            }

            $checked[] = $class;
        }

        return $checked;
    }

    /**
     * The checked reference of a single-reference key, or null when it is not declared.
     *
     * @template T of object
     *
     * @param  class-string<T>|null  $contract
     * @return ($contract is null ? class-string|null : class-string<T>|null)
     *
     * @throws InvalidReferenceException
     */
    public function one(BlueprintInterface $blueprint, string $key, ?string $contract = null): ?string
    {
        return $this->all($blueprint, $key, $contract)[0] ?? null;
    }
}
