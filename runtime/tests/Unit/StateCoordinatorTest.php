<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Runtime\Contracts\StatefulComponent;
use Gcego\Runtime\Exceptions\InvalidStateException;
use Gcego\Runtime\State\StateCoordinator;
use Gcego\Runtime\Tests\Fixtures\ExampleSearch;

function searchBlueprint(): Blueprint
{
    $shared = new ExampleSearch('search');

    return new Blueprint('subjects', $shared, $shared, new ExampleSearch('filters'));
}

it('creates one bucket per stateful occurrence, even for a reused instance', function (): void {
    expect((new StateCoordinator)->initialState(searchBlueprint()))->toBe([
        'subjects:search' => ['query' => '', 'page' => 1],
        'subjects:search#1' => ['query' => '', 'page' => 1],
        'subjects:filters' => ['query' => '', 'page' => 1],
    ]);
});

it('accepts declared fields through the component', function (): void {
    expect((new StateCoordinator)->accept(searchBlueprint(), 'state.subjects:search#1.page', '3'))
        ->toBe(['key' => 'subjects:search#1', 'field' => 'page', 'value' => 3]);
});

it('rejects unknown buckets, fields and shapes', function (string $property): void {
    (new StateCoordinator)->accept(searchBlueprint(), $property, 'x');
})->with([
    'whole state' => ['state'],
    'whole bucket' => ['state.subjects:search'],
    'unknown bucket' => ['state.subjects:other.query'],
    'unknown field' => ['state.subjects:search.isAdmin'],
    'too deep' => ['state.subjects:search.query.nested'],
    'other property' => ['blueprint'],
])->throws(InvalidStateException::class);

it('rejects values the component refuses', function (): void {
    (new StateCoordinator)->accept(searchBlueprint(), 'state.subjects:search.query', str_repeat('x', 51));
})->throws(InvalidStateException::class, 'must be a string of at most 50 characters');

it('sanitizes hydrated state against the definition', function (): void {
    $clean = (new StateCoordinator)->sanitize(searchBlueprint(), [
        'subjects:search' => ['query' => 'maths', 'injected' => 'x'],
        'attacker' => ['anything' => true],
        'subjects:filters' => 'not an array',
    ]);

    expect($clean)->toBe([
        'subjects:search' => ['query' => 'maths', 'page' => 1],
        'subjects:search#1' => ['query' => '', 'page' => 1],
        'subjects:filters' => ['query' => '', 'page' => 1],
    ]);
});

it('rejects invalid initial state declarations', function (): void {
    $bad = new class('bad') extends Component implements StatefulComponent
    {
        protected static function shorthand(): array
        {
            return [self::Name];
        }

        public function initialState(): array
        {
            return ['service' => new stdClass]; // @phpstan-ignore return.type
        }

        public function acceptState(string $field, mixed $value): mixed
        {
            return $value;
        }
    };

    (new StateCoordinator)->initialState(new Component(new Property('name', 'root'), $bad));
})->throws(InvalidStateException::class, 'must be null, a scalar or a list of scalars');
