<?php

declare(strict_types=1);

namespace Gcego\Components\Examples\Components;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Examples\Building\RevenueMetricBuilder;
use Gcego\Components\Examples\Configurations\Padding;
use Gcego\Components\Examples\Values\Money;
use Gcego\Components\Foundation\NamedComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\Field;
use Gcego\Components\Schema\Type;

final class RevenueMetric extends NamedComponent
{
    /** @return class-string<ComponentBuilder> */
    public static function builderClass(): string
    {
        return RevenueMetricBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('examples.revenueMetric')
            ->withFields([
                Field::of('amount', Type::instanceOf(Money::class))->mustExist(),
                Field::boolean('highlight')->defaultsTo(false),
            ])
            ->withAllowedConfigurations([Padding::class])
            ->withoutChildren();
    }
}
