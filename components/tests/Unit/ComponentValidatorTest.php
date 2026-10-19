<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Property;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Gcego\Components\Tests\Fixtures\OtherConfiguration;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Status;
use Gcego\Components\Tests\Fixtures\Style;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Components\Validation\ErrorCode;
use Gcego\Components\Validation\ValidationError;
use Gcego\Components\Validation\ValidationResult;

it('accepts correct definitions', function (): void {
    $component = new ParentComponent(
        FakeModel::class,
        new ExampleComponent('name', 'Name', new Property(ExampleComponent::Status, Status::Published)),
        new ChildComponent('code'),
        new Style('p-4'),
    );

    expect((new ComponentValidator)->validate($component)->isValid())->toBeTrue();
});

it('reports missing required properties', function (): void {
    $error = (new ComponentValidator)->validate(new ChildComponent)->first();

    expect($error?->code)->toBe(ErrorCode::MissingProperty)
        ->and($error?->subject)->toBe('name')
        ->and($error?->expected)->toBe('string')
        ->and($error?->received)->toBe('nothing');
});

it('reports wrong property types', function (mixed $value, string $received): void {
    $error = (new ExampleComponent('name', new Property(ExampleComponent::Searchable, $value)))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidType)
        ->and($error?->expected)->toBe('bool')
        ->and($error?->received)->toBe($received);
})->with([
    ['yes', 'string "yes"'],
    [1, 'int 1'],
    [null, 'null'],
    [[true], 'array(1)'],
]);

it('reports invalid enum values', function (): void {
    $error = (new ExampleComponent('name', new Property(ExampleComponent::Status, 'draft')))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidType)
        ->and($error?->expected)->toBe('enum<'.Status::class.'>');
});

it('reports values outside the allowed list', function (): void {
    $error = (new ExampleComponent('name', new Property(ExampleComponent::Format, 'markdown')))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidValue)
        ->and($error?->expected)->toBe('one of string "plain", string "html"');
});

it('formats actionable errors with expected, received and path', function (): void {
    $component = new Component(
        new Property(Component::Name, 'subjects'),
        new ExampleComponent('table', new Property(ExampleComponent::PerPage, 0)),
    );

    $error = $component->validate()->first();

    expect($error?->format())->toBe(<<<'TEXT'
        Invalid property "perPage" on component "fixtures.example".

        Expected: integer greater than zero
        Received: int 0

        Path: subjects.table
        TEXT)
        ->and((string) $error)->toBe($error?->format());
});

it('reports unknown properties with suggestions', function (): void {
    $error = (new ExampleComponent('name', new Property('serchable', true)))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::UnknownProperty)
        ->and($error?->hint)->toBe('Did you mean "searchable"?')
        ->and($error?->format())->toContain('Did you mean "searchable"?');
});

it('omits suggestions when nothing is close', function (): void {
    $error = (new ExampleComponent('name', new Property('completelyDifferent', true)))->validate()->first();

    expect($error?->hint)->toBeNull();
});

it('reports invalid explicit ids', function (): void {
    $error = (new Component(new Property(Component::Id, '1-bad id')))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidValue)
        ->and($error?->expected)->toBe('identifier matching '.Component::IdPattern);
});

it('reports duplicate explicit ids across the tree', function (): void {
    $result = (new Component(
        new Property(Component::Name, 'root'),
        new ChildComponent('a', new Property(Component::Id, 'dup')),
        new ChildComponent('b', new Property(Component::Id, 'dup')),
    ))->validate();

    expect($result->errorsWithCode(ErrorCode::DuplicateId))->toHaveCount(1)
        ->and($result->first()?->path)->toBe('root.b')
        ->and($result->first()?->received)->toBe('"dup", already used at root.a');
});

it('reports a reused instance with an explicit id as a duplicate', function (): void {
    $shared = new ChildComponent('shared', new Property(Component::Id, 'shared'));

    expect((new Component($shared, $shared))->validate()->errorsWithCode(ErrorCode::DuplicateId))->toHaveCount(1);
});

it('reports children on leaf components', function (): void {
    $definition = ExampleComponent::definition();
    $error = (new ComponentValidator)->validate(
        (new ExampleComponent('name'))->withChildren(new ChildComponent('x')),
    )->first();

    expect($definition->allowsChildren)->toBeFalse()
        ->and($error?->code)->toBe(ErrorCode::ChildrenNotAllowed)
        ->and($error?->received)->toBe('1 child component(s)');
});

it('reports invalid child types at the child path', function (): void {
    $error = (new ParentComponent(new Property(Component::Name, 'table'), new Component))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidChild)
        ->and($error?->path)->toBe('table.#0')
        ->and($error?->expected)->toBe('one of '.ExampleComponent::class.', '.ChildComponent::class);
});

it('reports invalid child counts', function (): void {
    $tooFew = (new ParentComponent)->validate()->first();
    $tooMany = (new ParentComponent(
        new ChildComponent('a'),
        new ChildComponent('b'),
        new ChildComponent('c'),
        new ChildComponent('d'),
    ))->validate()->first();

    expect($tooFew?->code)->toBe(ErrorCode::TooFewChildren)
        ->and($tooFew?->expected)->toBe('at least 1 child component(s)')
        ->and($tooMany?->code)->toBe(ErrorCode::TooManyChildren)
        ->and($tooMany?->expected)->toBe('at most 3 child component(s)');
});

it('reports configuration nodes the schema does not accept', function (): void {
    $error = (new ParentComponent(new ChildComponent('a'), new OtherConfiguration))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidConfiguration)
        ->and($error?->expected)->toBe('one of '.Style::class);
});

it('reports custom constraint failures', function (): void {
    $error = (new ParentComponent(new ChildComponent('a'), new ChildComponent('a')))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::ConstraintFailed)
        ->and($error?->message)->toBe('Child name "a" is used more than once.');
});

it('accepts constraints returning a single message or null', function (): void {
    $component = new class extends Component
    {
        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()
                ->withType('fixtures.constrained')
                ->withConstraint(fn (): ?string => null)
                ->withConstraint(fn (): string => 'Always fails.');
        }
    };

    expect($component->validate()->count())->toBe(1);
});

it('collects nested errors from every level', function (): void {
    $result = (new Component(
        new Property(Component::Name, 'root'),
        new ParentComponent(
            new Property(Component::Name, 'table'),
            new ExampleComponent(new Property(ExampleComponent::Searchable, 'yes')),
        ),
    ))->validate();

    expect($result->count())->toBe(2)
        ->and(array_map(fn (ValidationError $error): string => $error->path, $result->errors()))->toBe(['root.table.#0', 'root.table.#0'])
        ->and($result->errorsAt('root.table.#0'))->toHaveCount(2)
        ->and($result->errorsWithCode(ErrorCode::MissingProperty))->toHaveCount(1)
        ->and($result->errorsWithCode(ErrorCode::InvalidType))->toHaveCount(1);
});

it('throws a meaningful exception in strict mode', function (): void {
    $component = new ChildComponent(new Property(Component::Label, 5));

    try {
        (new ComponentValidator)->assertValid($component);
        $this->fail('Expected an exception.');
    } catch (InvalidComponentException $exception) {
        expect($exception->getMessage())->toStartWith('Component validation failed with 2 error(s).')
            ->and($exception->getMessage())->toContain('Missing required property "name" on component "fixtures.child".')
            ->and($exception->getMessage())->toContain('Path: #0')
            ->and($exception->result()->count())->toBe(2);
    }
});

it('truncates long error lists in exception messages', function (): void {
    $children = array_map(fn (): ChildComponent => new ChildComponent, range(1, 12));

    expect(fn () => (new Component(...$children))->assertValid())
        ->toThrow(InvalidComponentException::class, '... and 2 more error(s).');
});

it('does not throw in strict mode for valid trees', function (): void {
    (new ComponentValidator)->assertValid(new ChildComponent('a'));

    expect(true)->toBeTrue();
});

it('exposes results as arrays, strings and iterables', function (): void {
    $result = (new ChildComponent)->validate();
    $merged = $result->merge(new ValidationResult);

    expect($result->hasErrors())->toBeTrue()
        ->and($result->toArray()[0]['code'])->toBe('missing_property')
        ->and($result->format())->toContain('Path: #0')
        ->and(iterator_to_array($result))->toBe($result->errors())
        ->and($merged->count())->toBe(1)
        ->and((new ValidationResult)->first())->toBeNull();
});

it('truncates long string values in error messages', function (): void {
    $error = (new ExampleComponent('name', new Property(ExampleComponent::Searchable, str_repeat('x', 100))))->validate()->first();

    expect($error?->received)->toBe('string "'.str_repeat('x', 40).'..."');
});
