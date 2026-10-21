<?php

declare(strict_types=1);

namespace Gcego\Components\Concerns;

use Gcego\Components\Contracts\Concern;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * State for components that hold a value:
 *
 *     TextInput::make('title')
 *         ->default('Untitled')
 *         ->live()                       // optional: dependents are detected automatically
 *         ->dehydrated(false)            // keep out of dehydrate()
 *         ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))
 */
final class HasState implements Concern
{
    public static function properties(): array
    {
        return [
            ...HasStatePath::properties(),
            new PropertyDefinition('default', Type::mixed(), nullable: true),
            new PropertyDefinition('live', Type::bool()),
            new PropertyDefinition('dehydrated', Type::bool()),
            new PropertyDefinition('afterStateUpdated', Type::instanceOf(Computed::class)),
        ];
    }
}
