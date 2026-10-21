<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Countable;
use Gcego\Blueprints\Contracts\BlueprintDefinition;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\Naming;

/**
 * A definition holding an ordered list of distinct lowercase identifiers,
 * for extension packages: Permissions::make(['view', 'create', 'subjects.export']).
 *
 * Identifiers are letters and digits separated by ".", "_" or "-", starting
 * with a letter, at most 100 characters. The list must be a non-empty list.
 */
abstract readonly class IdentifierListDefinition implements BlueprintDefinition, Countable
{
    /**
     * @param  list<string>  $identifiers
     */
    final protected function __construct(private array $identifiers) {}

    /**
     * @param  list<string>  $identifiers
     *
     * @throws InvalidDefinitionException
     */
    public static function make(array $identifiers): static
    {
        return new static(self::normalize(static::definitionKey(), $identifiers));
    }

    final public function key(): string
    {
        return static::definitionKey();
    }

    /**
     * @return list<string>
     */
    public function value(): array
    {
        return $this->identifiers;
    }

    public function contains(string $identifier): bool
    {
        return in_array($identifier, $this->identifiers, true);
    }

    public function count(): int
    {
        return count($this->identifiers);
    }

    /**
     * @param  array<mixed>  $identifiers
     * @return list<string>
     *
     * @throws InvalidDefinitionException
     */
    final public static function normalize(string $key, array $identifiers, ?string $option = null): array
    {
        $where = $option === null ? '' : sprintf('option "%s": ', $option);

        if (! array_is_list($identifiers)) {
            throw InvalidDefinitionException::because($key, $where.'expected a list of identifiers, not an array with keys.');
        }

        if ($identifiers === []) {
            throw InvalidDefinitionException::because($key, $where.'expected at least one identifier.', 'Omit the definition instead of passing an empty list.');
        }

        $seen = [];

        foreach ($identifiers as $index => $identifier) {
            if (! is_string($identifier) || ! Naming::isIdentifier($identifier)) {
                throw InvalidDefinitionException::because($key, sprintf(
                    '%selement %d: %s is not a lowercase identifier such as "view" or "subjects.export".',
                    $where,
                    $index,
                    is_string($identifier) ? '"'.$identifier.'"' : get_debug_type($identifier),
                ));
            }

            if (isset($seen[$identifier])) {
                throw InvalidDefinitionException::because($key, sprintf('%s"%s" is listed twice (elements %d and %d).', $where, $identifier, $seen[$identifier], $index));
            }

            $seen[$identifier] = $index;
        }

        /** @var list<string> $identifiers */
        return $identifiers;
    }
}
