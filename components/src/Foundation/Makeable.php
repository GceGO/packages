<?php

declare(strict_types=1);

namespace Gcego\Components\Foundation;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Facades\Components;

/**
 * The Filament-style entry point: TextInput::make('email')->email()->required().
 *
 * make() starts a define() builder, passes it through the class's setUp()
 * hook, then through the application's configureUsing() modifiers.
 */
trait Makeable
{
    public static function make(mixed ...$items): ComponentBuilder
    {
        return Components::configurator()->configure(static::setUp(static::define(...$items)));
    }
}
