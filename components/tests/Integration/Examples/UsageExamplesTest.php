<?php

declare(strict_types=1);

use Gcego\Components\Facades\Components;
use Gcego\Components\Layout\Schema;

it('executes the complete documented dashboard declaration', function (): void {
    $schema = require dirname(__DIR__, 3).'/examples/full-usage.php';

    expect($schema)->toBeInstanceOf(Schema::class)
        ->and($schema->validate()->isValid())->toBeTrue()
        ->and($schema->tree()->count())->toBe(49)
        ->and($schema->tree()->duplicateIds())->toBe([])
        ->and($schema->tree()->findById('users-table')?->path->toString())->toBe('admin.dashboard.workspace.overview.users')
        ->and(Components::inspect($schema)->isValid())->toBeTrue();

    $normalized = $schema->normalize();
    expect($normalized['type'])->toBe('schema')
        ->and($normalized['properties']['name'])->toBe('admin')
        ->and(json_decode(json_encode($normalized, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->toBe($normalized);
});

it('executes the custom builder value and configuration example', function (): void {
    $schema = require dirname(__DIR__, 3).'/examples/custom-component.php';
    $metric = $schema->children()->first();

    expect($schema->validate()->isValid())->toBeTrue()
        ->and($metric->type())->toBe('examples.revenueMetric')
        ->and($metric->get('highlight'))->toBeTrue();

    $normalized = $metric->normalize();
    expect($normalized['properties']['amount'])->toBe(['minorUnits' => 250000, 'currency' => 'XAF'])
        ->and($normalized['configurations'][0]['data'])->toBe(['padding' => 16])
        ->and(Components::catalog()->resolve('revenueMetric'))->toBe($metric::class);
});
