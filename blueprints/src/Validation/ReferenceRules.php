<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

use ArrayIterator;
use Countable;
use Gcego\Blueprints\Contracts\BlueprintDeriver;
use Gcego\Blueprints\Definitions\Derive;
use Gcego\Blueprints\Validation\Rules\ClassExists;
use Gcego\Blueprints\Validation\Rules\ImplementsContract;
use IteratorAggregate;
use LogicException;
use Traversable;

/**
 * The installed reference rules (a container singleton).
 *
 * Adapters add theirs from a service provider:
 *
 *     $this->callAfterResolving(ReferenceRules::class, fn (ReferenceRules $rules) => $rules->add(...));
 *
 * The set is locked the first time a validator uses it, so every blueprint
 * of a process is checked against the same rules: a rule added later would
 * leave earlier blueprints unchecked.
 *
 * @implements IteratorAggregate<int, ReferenceRule>
 */
final class ReferenceRules implements Countable, IteratorAggregate
{
    /**
     * @var list<ReferenceRule>
     */
    private array $rules;

    private bool $locked = false;

    public function __construct(ReferenceRule ...$rules)
    {
        $this->rules = array_values($rules);
    }

    /**
     * Class existence for every reference, and the deriver contract for Derive.
     */
    public static function defaults(): self
    {
        return new self(new ClassExists, new ImplementsContract(Derive::Key, BlueprintDeriver::class));
    }

    /**
     * @throws LogicException once the rules are in use.
     */
    public function add(ReferenceRule $rule): self
    {
        if ($this->locked) {
            throw new LogicException(sprintf(
                'Cannot add %s: blueprints were already validated with these reference rules. Add rules from a service provider\'s register() method.',
                $rule::class,
            ));
        }

        $this->rules[] = $rule;

        return $this;
    }

    /**
     * Refuse further rules. Called by BlueprintValidator before its first validation.
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @return list<ReferenceRule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function count(): int
    {
        return count($this->rules);
    }

    /**
     * @return Traversable<int, ReferenceRule>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rules);
    }
}
