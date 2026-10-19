<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A registered report shown on a dashboard (its first page, with a link to the full report):
 *
 *     new ReportSection('student-performance')
 */
final class ReportSection extends AnalyticsNode
{
    public function report(): string
    {
        return $this->nodeName();
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.report-section')
            ->withoutProperties(self::Id)
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a report name'))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
