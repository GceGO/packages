<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Registry\BlueprintProvider;
use Gcego\Blueprints\Registry\ProvidesBlueprint;
use Illuminate\Contracts\Config\Repository;

/**
 * A class-based provider listed in gcego-registry.providers, using constructor injection.
 */
final readonly class ReportsBlueprint implements BlueprintProvider
{
    use ProvidesBlueprint;

    public function __construct(private Repository $config) {}

    public function blueprint(): Blueprint
    {
        $app = $this->config->get('app.name');

        return Blueprint::define(
            Name::make('reports'),
            Title::make('Reports of '.(is_string($app) ? $app : 'GCEGO')),
            Table::make(PlainTable::class),
        );
    }
}
