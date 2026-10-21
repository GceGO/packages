<?php

declare(strict_types=1);

namespace Gcego\Components\Foundation;

use Gcego\Components\Concerns\CanBeHidden;
use Gcego\Components\Concerns\CanSpanColumns;
use Gcego\Components\Concerns\HasExtraAttributes;
use Gcego\Components\Schema\ComponentDefinition;

/**
 * The base of every catalog component that can be placed in a schema.
 *
 *     Section::make('profile')
 *         ->columns(['default' => 1, 'lg' => 2])
 *         ->visible(fn (string $operation): bool => $operation !== 'create')
 *         ->schema([
 *             TextInput::make('name')->required()->columnSpanFull(),
 *         ])
 *
 * It adds make(), visibility, column spans and extra attributes to the named
 * component contract.
 */
abstract class SchemaComponent extends NamedComponent
{
    use Makeable;

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withConcerns(
            CanBeHidden::class,
            CanSpanColumns::class,
            HasExtraAttributes::class,
        );
    }
}
