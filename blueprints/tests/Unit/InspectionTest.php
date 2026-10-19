<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Inspection\BlueprintInspector;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\ResourceBlueprint;

it('inspects a valid blueprint', function (): void {
    $inspection = (new BlueprintInspector)->inspect(new Blueprint(
        'subjects',
        new ExampleTable('table', new ExampleColumn('name')),
        new ExampleAction('create'),
        new ExampleAction('edit'),
    ));

    expect($inspection->name)->toBe('subjects')
        ->and($inspection->title)->toBe('Subjects')
        ->and($inspection->slug)->toBe('subjects')
        ->and($inspection->class)->toBe(Blueprint::class)
        ->and($inspection->type)->toBe('blueprint')
        ->and($inspection->componentCount)->toBe(5)
        ->and($inspection->parts)->toBe(['table' => ['subjects.table'], 'action' => ['subjects.create', 'subjects.edit']])
        ->and($inspection->missingRequiredRoles)->toBe([])
        ->and($inspection->missingOptionalRoles)->toBe([])
        ->and($inspection->isValid())->toBeTrue()
        ->and($inspection->normalized['blueprint']['name'])->toBe('subjects');
});

it('reports conflicts and missing roles against the policy', function (): void {
    $inspection = (new BlueprintInspector)->inspect(new ResourceBlueprint('subjects', new ExampleAction('a')));

    expect($inspection->missingRequiredRoles)->toBe(['table'])
        ->and($inspection->missingOptionalRoles)->toBe(['source', 'form'])
        ->and($inspection->conflicts)->toHaveCount(1)
        ->and($inspection->isValid())->toBeFalse()
        ->and($inspection->toArray())->toHaveKeys(['name', 'parts', 'conflicts', 'valid', 'errors', 'normalized'])
        ->and($inspection->toArray()['valid'])->toBeFalse();
});
