<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A KPI card for a registered metric (inline Metric children are KPI cards too):
 *
 *     new Kpi('total_students')
 *     new Kpi('pass_rate', 'Pass rate (this term)')
 */
final class Kpi extends AnalyticsNode
{
    public function metric(): string
    {
        return $this->nodeName();
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.kpi')
            ->withoutProperties(self::Id)
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a metric name'))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
