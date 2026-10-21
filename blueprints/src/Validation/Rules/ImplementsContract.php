<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation\Rules;

use Gcego\Blueprints\Validation\BlueprintErrorCode;
use Gcego\Blueprints\Validation\ReferenceRule;

/**
 * References of one definition key must implement or extend a contract.
 *
 * The contract is a class name string, so an adapter can be declared without
 * gcego/blueprints importing the optional package that owns the contract:
 *
 *     new ImplementsContract(Table::Key, \Gcego\Tables\Contracts\TableDefinition::class)
 */
final readonly class ImplementsContract implements ReferenceRule
{
    /**
     * @param  class-string  $contract
     */
    public function __construct(
        public string $key,
        public string $contract,
    ) {}

    public function appliesTo(string $key): bool
    {
        return $key === $this->key;
    }

    public function check(string $key, string $class): ?string
    {
        return is_a($class, $this->contract, true)
            ? null
            : sprintf('%s must implement %s to be used as a "%s" reference.', $class, $this->contract, $this->key);
    }

    public function code(): string
    {
        return BlueprintErrorCode::IncompatibleReference->value;
    }
}
