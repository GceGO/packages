<?php

declare(strict_types=1);

/**
 * Repeatable micro-benchmark for construction, lookup, validation, traversal
 * and normalization of a page-sized component tree.
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [sections] [fieldsPerSection] [iterations])
 */

use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Validation\ComponentValidator;

require __DIR__.'/../vendor/autoload.php';

$sections = max(1, (int) ($argv[1] ?? 50));
$fieldsPerSection = max(1, min(3, (int) ($argv[2] ?? 3)));
$iterations = max(1, (int) ($argv[3] ?? 20));

$build = static function () use ($sections, $fieldsPerSection): Component {
    $children = [];

    for ($section = 0; $section < $sections; $section++) {
        $fields = [];

        for ($field = 0; $field < $fieldsPerSection; $field++) {
            $fields[] = new ExampleComponent('field'.$field, 'Field '.$field, new Property(ExampleComponent::Searchable, true));
        }

        $children[] = new ParentComponent(FakeModel::class, new Property(Component::Name, 'section'.$section), ...$fields);
    }

    return new Component(new Property(Component::Name, 'page'), ...$children);
};

/**
 * @param  Closure(): mixed  $operation
 */
$measure = static function (string $label, Closure $operation) use ($iterations): void {
    $operation();
    $start = hrtime(true);

    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $operation();
    }

    printf("%-14s %9.3f ms/op\n", $label, (hrtime(true) - $start) / 1e6 / $iterations);
};

$tree = $build();
$nodeCount = $tree->tree()->count();
$walker = new ComponentWalker;
$validator = new ComponentValidator;

printf("Tree: %d components, %d iterations, PHP %s\n\n", $nodeCount, $iterations, PHP_VERSION);

$measure('construct', $build);
$measure('lookup', static function () use ($tree): void {
    foreach ($tree->children() as $section) {
        foreach ($section->children() as $field) {
            $field->get(ExampleComponent::Searchable);
            $field->get(ExampleComponent::PerPage);
        }
    }
});
$measure('traverse', static function () use ($walker, $tree): void {
    foreach ($walker->preOrder($tree) as $node) {
        $node->path->toString();
    }
});
$measure('validate', static fn () => $validator->validate($tree));
$measure('normalize', static fn () => $tree->normalize());

printf("\nPeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);
