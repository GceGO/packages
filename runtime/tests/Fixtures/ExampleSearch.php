<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Runtime\Contracts\StatefulComponent;
use Gcego\Runtime\Exceptions\InvalidStateException;

/**
 * A stateful part: a search box with a query and a page number.
 */
final class ExampleSearch extends Component implements BlueprintPart, StatefulComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.search')->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple('search');
    }

    public function initialState(): array
    {
        return ['query' => '', 'page' => 1];
    }

    /**
     * @return string|int
     */
    public function acceptState(string $field, mixed $value): mixed
    {
        return match ($field) {
            'query' => is_string($value) && mb_strlen($value) <= 50
                ? trim($value)
                : throw InvalidStateException::rejected($field, 'must be a string of at most 50 characters.'),
            'page' => is_numeric($value) && (int) $value >= 1
                ? (int) $value
                : throw InvalidStateException::rejected($field, 'must be a positive integer.'),
            default => throw InvalidStateException::unknownPath($field),
        };
    }
}
