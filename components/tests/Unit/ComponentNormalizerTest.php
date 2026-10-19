<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Contracts\ValueObject;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Normalization\RuntimePlaceholder;
use Gcego\Components\Property;
use Gcego\Components\Tests\Fixtures\Callback;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Gcego\Components\Tests\Fixtures\Money;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Status;
use Gcego\Components\Tests\Fixtures\Style;

it('normalizes a component tree', function (): void {
    $component = new ParentComponent(
        FakeModel::class,
        new Property(Component::Name, 'subjects'),
        new Property(Component::Id, 'subjects-table'),
        new ChildComponent('code'),
        new Style('p-4'),
    );

    expect((new ComponentNormalizer)->normalize($component))->toBe([
        'type' => 'fixtures.parent',
        'class' => ParentComponent::class,
        'id' => 'subjects-table',
        'path' => 'subjects',
        'properties' => [
            'id' => 'subjects-table',
            'model' => FakeModel::class,
            'name' => 'subjects',
        ],
        'configurations' => [
            ['class' => Style::class, 'data' => ['classes' => 'p-4']],
        ],
        'children' => [
            [
                'type' => 'fixtures.child',
                'class' => ChildComponent::class,
                'id' => null,
                'path' => 'subjects.code',
                'properties' => ['name' => 'code'],
                'configurations' => [],
                'children' => [],
            ],
        ],
    ]);
});

it('includes effective defaults and keeps enums as cases', function (): void {
    $properties = (new ExampleComponent('name'))->normalize()['properties'];

    expect($properties)->toBe([
        'format' => 'plain',
        'name' => 'name',
        'perPage' => 10,
        'searchable' => false,
        'status' => Status::Draft,
    ]);
});

it('is deterministic regardless of property declaration order', function (): void {
    $first = new ExampleComponent(new Property(Component::Name, 'a'), new Property(Component::Label, 'A'));
    $second = new ExampleComponent(new Property(Component::Label, 'A'), new Property(Component::Name, 'a'));

    expect($first->normalize())->toBe($second->normalize())
        ->and($first->normalize())->toBe($first->normalize());
});

it('produces stable paths for nested children', function (): void {
    $normalized = (new Component(
        new Property(Component::Name, 'root'),
        new ChildComponent('a', new ChildComponent('b')),
        new ChildComponent('a'),
    ))->normalize();

    expect($normalized['path'])->toBe('root');

    /** @var list<array{path: string, children: list<array{path: string}>}> $children */
    $children = $normalized['children'];

    expect($children[0]['path'])->toBe('root.a')
        ->and($children[0]['children'][0]['path'])->toBe('root.a.b')
        ->and($children[1]['path'])->toBe('root.a#1');
});

it('applies property normalizers', function (): void {
    expect((new ExampleComponent('name', '  Padded  '))->normalize()['properties'])->toHaveKey('label', 'Padded');
});

it('normalizes value objects and hides runtime values', function (): void {
    $properties = (new ExampleComponent(
        'name',
        new Property(ExampleComponent::Price, new Money(500, 'XAF')),
        new Property(ExampleComponent::Formatter, new Callback(fn (): string => 'secret')),
    ))->normalize()['properties'];

    expect($properties)->toHaveKey('price', ['amount' => 500, 'currency' => 'XAF'])
        ->and($properties)->toHaveKey('formatter')
        ->and(is_array($properties) ? $properties['formatter'] : null)->toEqual(new RuntimePlaceholder(Callback::class));
});

it('normalizes nested arrays of supported values', function (): void {
    $normalizer = new ComponentNormalizer;

    expect($normalizer->normalizeValue(['a' => [Status::Draft, new Money(1, 'XAF')], 'b' => null]))
        ->toBe(['a' => [Status::Draft, ['amount' => 1, 'currency' => 'XAF']], 'b' => null]);
});

it('rejects values that cannot be normalized', function (): void {
    (new ComponentNormalizer)->normalizeValue(new stdClass, 'test value');
})->throws(InvalidConfigurationException::class, 'Value at test value (stdClass) cannot be normalized');

it('rejects value objects that normalize to unsupported data', function (): void {
    $leaky = new readonly class implements ValueObject
    {
        public function normalize(): object
        {
            return new stdClass;
        }
    };

    (new Component(new Property('leaky', $leaky)))->normalize();
})->throws(InvalidConfigurationException::class, '#0 property "leaky"');

it('rejects configuration nodes that normalize to unsupported data', function (): void {
    $leaky = new readonly class implements ConfigurationNode
    {
        public function normalize(): Closure
        {
            return fn (): null => null;
        }
    };

    (new Component($leaky))->normalize();
})->throws(InvalidConfigurationException::class, '#0 configuration #0');

it('rejects self-referencing value objects', function (): void {
    $recursive = new class implements ValueObject
    {
        public function normalize(): self
        {
            return $this;
        }
    };

    (new ComponentNormalizer)->normalizeValue($recursive);
})->throws(InvalidConfigurationException::class, 'cannot be normalized');

it('optionally includes schema metadata', function (): void {
    $normalized = (new ComponentNormalizer(includeSchema: true))->normalize(new ChildComponent('a'));

    expect($normalized)->toHaveKey('schema')
        ->and(is_array($normalized['schema']) ? $normalized['schema']['type'] : null)->toBe('fixtures.child');
});
