<?php

declare(strict_types=1);

use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Tests\TestCase;

pest()->afterEach(function (): void {
    DefinitionCatalog::reset();
})->in('Unit');

pest()->extend(TestCase::class)->in('Integration');
