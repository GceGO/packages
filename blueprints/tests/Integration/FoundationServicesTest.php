<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Normalization\BlueprintNormalizer;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Components\Exceptions\InvalidChildException;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Traversal\ComponentWalker;

it('uses the registered walker for blueprint composition', function (): void {
    app()->instance(ComponentWalker::class, new ComponentWalker(maxDepth: 0));

    $blueprint = new Blueprint('subjects', new ExampleTable('table'));

    expect(fn () => $blueprint->composition())->toThrow(InvalidChildException::class);
});

it('uses the registered normalizer for blueprint output', function (): void {
    app()->instance(ComponentNormalizer::class, new ComponentNormalizer(
        app(ComponentWalker::class),
        includeSchema: true,
    ));

    $normalized = (new BlueprintNormalizer)->normalize(new Blueprint('subjects'));

    expect($normalized['component'])->toHaveKey('schema');
});
