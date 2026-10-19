<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Normalization\BlueprintNormalizer;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleSource;
use Gcego\Blueprints\Tests\Fixtures\ExampleStyle;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\FakeModel;
use Gcego\Components\Exceptions\InvalidPropertyException;
use Gcego\Components\Property;

function normalizedBlueprint(): Blueprint
{
    return new Blueprint(
        'subjects',
        new Property(Blueprint::Title, 'Subject Management'),
        new ExampleSource(FakeModel::class),
        new ExampleTable('table', new ExampleColumn('name', 'Subject Name')),
        new ExampleAction('create'),
        new ExampleAction('edit'),
        new ExampleStyle(['rounded-2xl']),
    );
}

it('normalizes metadata, parts and roles', function (): void {
    $normalized = (new BlueprintNormalizer)->normalize(normalizedBlueprint());

    expect($normalized['blueprint'])->toBe([
        'name' => 'subjects',
        'title' => 'Subject Management',
        'slug' => 'subjects',
        'description' => null,
        'icon' => null,
        'titleDerived' => false,
        'slugDerived' => true,
        'class' => Blueprint::class,
        'type' => 'blueprint',
    ])
        ->and($normalized['policy'])->toBe(['requiredRoles' => [], 'allowedRoles' => null, 'singleRoles' => []])
        ->and($normalized['parts'])->toBe([
            ['role' => 'source', 'multiple' => false, 'path' => 'subjects.#0', 'class' => ExampleSource::class, 'type' => 'fixtures.ExampleSource'],
            ['role' => 'table', 'multiple' => false, 'path' => 'subjects.table', 'class' => ExampleTable::class, 'type' => 'fixtures.ExampleTable'],
            ['role' => 'action', 'multiple' => true, 'path' => 'subjects.create', 'class' => ExampleAction::class, 'type' => 'fixtures.ExampleAction'],
            ['role' => 'action', 'multiple' => true, 'path' => 'subjects.edit', 'class' => ExampleAction::class, 'type' => 'fixtures.ExampleAction'],
        ])
        ->and($normalized['roles'])->toBe([
            'action' => ['multiple' => true, 'paths' => ['subjects.create', 'subjects.edit']],
            'source' => ['multiple' => false, 'paths' => ['subjects.#0']],
            'table' => ['multiple' => false, 'paths' => ['subjects.table']],
        ]);
});

it('reuses component normalization for the tree', function (): void {
    $component = (new BlueprintNormalizer)->normalize(normalizedBlueprint())['component'];

    expect($component)->toBe(normalizedBlueprint()->normalize())
        ->and($component['configurations'])->toBe([['class' => ExampleStyle::class, 'data' => ['classes' => ['rounded-2xl']]]]);
});

it('is deterministic and equal for equivalent declarations', function (): void {
    $short = new Blueprint('subjects', new ExampleTable('table'));
    $long = new Blueprint(new Property(Blueprint::Name, 'subjects'), new ExampleTable(new Property(ExampleTable::Name, 'table')));

    expect((new BlueprintNormalizer)->normalize($short))->toBe((new BlueprintNormalizer)->normalize($long))
        ->and((new BlueprintNormalizer)->normalize(normalizedBlueprint()))->toBe((new BlueprintNormalizer)->normalize(normalizedBlueprint()));
});

it('can include component schemas', function (): void {
    $component = (new BlueprintNormalizer(includeSchema: true))->normalize(new Blueprint('subjects'))['component'];

    expect($component)->toHaveKey('schema');
});

it('normalizes unnamed blueprints without inventing identity', function (): void {
    $normalized = (new BlueprintNormalizer)->normalize(new Blueprint);

    expect($normalized['blueprint']['name'])->toBeNull()
        ->and($normalized['blueprint']['slug'])->toBeNull();
});

it('rejects unsupported values before they reach normalization', function (): void {
    new Blueprint('subjects', new Property(Blueprint::Description, fn (): string => 'callback'));
})->throws(InvalidPropertyException::class);
