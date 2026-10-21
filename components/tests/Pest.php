<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

pest()->beforeEach(function (): void {
    $app = new Application(dirname(__DIR__));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $provider = new ComponentServiceProvider($app);
    $app->addDeferredServices(array_fill_keys($provider->provides(), ComponentServiceProvider::class));
})->afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    Container::setInstance(null);
})->in('Unit', 'Integration');
