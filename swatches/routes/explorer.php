<?php

declare(strict_types=1);

use Gcego\Swatches\Http\ExplorerController;
use Gcego\Swatches\SwatchesConfig;
use Illuminate\Support\Facades\Route;

$config = app(SwatchesConfig::class);

Route::middleware($config->explorerMiddleware)
    ->get($config->explorerPath, ExplorerController::class)
    ->name('swatches.explorer');
