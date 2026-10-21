<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Component;
use Gcego\Components\Concerns\HasColumns;
use Gcego\Components\Concerns\HasStatePath;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Schema\ComponentDefinition;

/**
 * The root of a layout:
 *
 *     Schema::make()->columns(2)->components([...])
 */
class Schema extends Component
{
    use Makeable;

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('schema')
            ->withConcerns(HasColumns::class, HasStatePath::class);
    }
}
