<?php

declare(strict_types=1);

/**
 * Repeatable micro-benchmark for gcego/blueprints.
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [blueprints] [iterations])
 *
 * Each blueprint has a source, a table with 5 columns, a form with 5 fields,
 * and 3 actions (15 components).
 */

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintCollection;
use Gcego\Blueprints\Normalization\BlueprintNormalizer;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleSource;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\FakeModel;
use Gcego\Blueprints\Validation\BlueprintValidator;

require __DIR__.'/../vendor/autoload.php';

$count = max(1, (int) ($argv[1] ?? 300));
$iterations = max(1, (int) ($argv[2] ?? 10));

/**
 * @return list<Blueprint>
 */
$build = static function () use ($count): array {
    $blueprints = [];

    for ($index = 0; $index < $count; $index++) {
        $columns = array_map(static fn (int $column): ExampleColumn => new ExampleColumn('c'.$column, 'Column '.$column), range(1, 5));

        $blueprints[] = new Blueprint(
            'feature-'.$index,
            new ExampleSource(FakeModel::class),
            new ExampleTable('table', ...$columns),
            new ExampleForm('form', ...$columns),
            new ExampleAction('create'),
            new ExampleAction('edit'),
            new ExampleAction('delete'),
        );
    }

    return $blueprints;
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

    printf("%-34s %9.3f ms/op\n", $label, (hrtime(true) - $start) / 1e6 / $iterations);
};

printf("gcego/blueprints benchmark: %d blueprints x 15 components, %d iterations, PHP %s\n\n", $count, $iterations, PHP_VERSION);

$measure('construct', $build);

$measure('construct + compose (role lookup)', static function () use ($build): void {
    foreach ($build() as $blueprint) {
        $blueprint->part('table');
        $blueprint->parts('action');
    }
});

$blueprints = $build();

foreach ($blueprints as $blueprint) {
    $blueprint->composition();
}

$measure('cached role lookup', static function () use ($blueprints): void {
    foreach ($blueprints as $blueprint) {
        $blueprint->part('table');
        $blueprint->parts('action');
        $blueprint->hasPart('form');
    }
});

$validator = new BlueprintValidator;
$measure('validate (structured)', static function () use ($blueprints, $validator): void {
    foreach ($blueprints as $blueprint) {
        $validator->validate($blueprint);
    }
});

$normalizer = new BlueprintNormalizer;
$measure('normalize', static function () use ($blueprints, $normalizer): void {
    foreach ($blueprints as $blueprint) {
        $normalizer->normalize($blueprint);
    }
});

$measure('collect into BlueprintCollection', static fn () => new BlueprintCollection(...$blueprints));

printf("\nPeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);
