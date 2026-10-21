<?php

declare(strict_types=1);
use Gcego\Components\ComponentServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->beforeEach(function (): void {
    $app = new Application(dirname(__DIR__));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $app->register(ComponentServiceProvider::class);
})->afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    Container::setInstance(null);
})->in('Unit', 'Integration');
