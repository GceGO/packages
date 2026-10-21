<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

use Gcego\Components\Schema\PropertyDefinition;

/**
 * A reusable set of property definitions a component opts into:
 *
 *     parent::defineSchema()->withConcerns(CanBeHidden::class, HasColumns::class)
 */
interface Concern
{
    /**
     * @return list<PropertyDefinition>
     */
    public static function properties(): array;
}
