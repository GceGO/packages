<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Style;
use Gcego\Components\Trees\TreeNode;
use Gcego\Components\Validation\ErrorCode;

/**
 * Mirrors the future Blueprint/Table/Form acceptance scenario with fixtures.
 */
function blueprintFixture(): Component
{
    return new Component(
        new Property(Component::Name, 'subjects'),
        new Property('title', 'Subject Management'),

        new ParentComponent(
            new Property(Component::Name, 'table'),
            new Property(ParentComponent::Model, FakeModel::class),
            new Style('rounded-lg shadow'),

            new ExampleComponent(
                new Property(ExampleComponent::Name, 'name'),
                new Property(ExampleComponent::Label, 'Subject Name'),
                new Property(ExampleComponent::Searchable, true),
            ),
        ),

        new Component(
            new Property(Component::Name, 'form'),
            new ChildComponent('name', new Property(ChildComponent::Label, 'Subject Name')),
        ),

        new Component(new Property(Component::Name, 'create')),
        new Component(new Property(Component::Name, 'edit')),
    );
}

it('composes multiple levels of siblings and descendants', function (): void {
    $tree = blueprintFixture()->tree();

    expect(array_map(fn (TreeNode $node): string => $node->path->toString(), $tree->depthFirst()))->toBe([
        'subjects',
        'subjects.table',
        'subjects.table.name',
        'subjects.form',
        'subjects.form.name',
        'subjects.create',
        'subjects.edit',
    ])
        ->and($tree->depth())->toBe(2)
        ->and($tree->find('subjects.table.name')?->component->get(ExampleComponent::Searchable))->toBeTrue();
});

it('validates the full acceptance tree', function (): void {
    expect(blueprintFixture()->validate()->isValid())->toBeTrue();
});

it('reports nested errors with full paths', function (): void {
    $root = new Component(
        new Property(Component::Name, 'subjects'),
        new ParentComponent(
            new Property(Component::Name, 'table'),
            new ExampleComponent('name', new Property('serchable', true)),
            new Component(new Property(Component::Name, 'stray')),
        ),
    );

    $result = $root->validate();

    expect($result->count())->toBe(2)
        ->and($result->errorsWithCode(ErrorCode::UnknownProperty)[0]->path)->toBe('subjects.table.name')
        ->and($result->errorsWithCode(ErrorCode::InvalidChild)[0]->path)->toBe('subjects.table.stray');
});

it('keeps configuration nodes out of the renderable tree', function (): void {
    $tree = blueprintFixture()->tree();
    $table = $tree->find('subjects.table');

    expect($table?->component->childCount())->toBe(1)
        ->and($table?->component->configurations()->first(Style::class)?->classes)->toBe('rounded-lg shadow')
        ->and($tree->findByType(Style::class))->toBe([]);
});

it('normalizes the full acceptance tree deterministically', function (): void {
    expect(blueprintFixture()->normalize())->toBe(blueprintFixture()->normalize());
});
