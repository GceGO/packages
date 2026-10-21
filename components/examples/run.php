<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Components\Facades\Components;
use Gcego\Components\Layout\Schema;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

require dirname(__DIR__).'/vendor/autoload.php';

// Bootstrap only for this standalone demonstration. Laravel does this in a real app.
$app = new Application(dirname(__DIR__));
Facade::setFacadeApplication($app);
$app->register(ComponentServiceProvider::class);

$arguments = $argv ?? [];

try {
    $schema = require __DIR__.(in_array('--custom', $arguments, true) ? '/custom-component.php' : '/full-usage.php');

    if (! $schema instanceof Schema) {
        throw new RuntimeException('The example must return a layout schema.');
    }

    $schema->assertValid();
    $tree = Components::tree($schema);
    $inspection = Components::inspect($schema);

    if (in_array('--normalized', $arguments, true)) {
        // This example uses scalar/array values. Other transports must handle enums/placeholders explicitly.
        echo json_encode(Components::normalize($schema), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    } else {
        echo sprintf(
            "Valid layout: %d components, depth %d, %d validation errors.\n",
            $tree->count(),
            $tree->depth(),
            count($inspection->errors),
        );
        $usersTable = $tree->findById('users-table');

        if ($usersTable !== null) {
            echo 'Users table: '.$usersTable->path->toString().PHP_EOL;
        }
        echo 'Run with --normalized to print the complete sample definition.'.PHP_EOL;
    }
} finally {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    $app->forgetScopedInstances();
}
