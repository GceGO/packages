<?php

declare(strict_types=1);

use Gcego\Luminaries\Http\ExplorerController;
use Gcego\Luminaries\LuminariesConfig;
use Illuminate\Support\Facades\Route;

$config = app(LuminariesConfig::class);

Route::middleware($config->explorerMiddleware)
    ->get($config->explorerPath, ExplorerController::class)
    ->name('luminaries.explorer');
