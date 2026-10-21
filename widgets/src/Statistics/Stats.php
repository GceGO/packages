<?php

declare(strict_types=1);

namespace Gcego\Widgets\Statistics;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Widgets\Presets\WidgetPresets;
use Gcego\Widgets\Widget;

/**
 * A row of KPI cards, in order, in a responsive grid of equal-height cards:
 *
 *     new Stats(
 *         new Property(Stats::Name, 'school_overview'),
 *         new Stat('Students', StudentCountProvider::class),
 *         new Stat('Teachers', TeacherCountProvider::class),
 *         new Stat('Subjects', new Aggregate(Subject::class)),
 *     )
 *
 * Each stat keeps its own data source, requirements and visibility. A
 * shared DataProvider on the group may return WidgetDataResult::values()
 * keyed by stat name for stats without their own source. The group's
 * requirements apply to every stat.
 */
final class Stats extends Widget
{
    /** Columns from the md breakpoint up (default: the number of stats, at most 4). */
    public const string Columns = 'columns';

    /**
     * The Name, or one derived from the label, or from the stats it holds ("students_teachers_subjects").
     */
    public function widgetName(): string
    {
        $name = parent::widgetName();

        if ($name !== '') {
            return $name;
        }

        return mb_substr(implode('_', array_map(static fn (Stat $stat): string => $stat->widgetName(), $this->stats())), 0, 64);
    }

    public function view(): string
    {
        return 'gcego-widgets::statistics.stats';
    }

    /**
     * @return list<Stat>
     */
    public function stats(): array
    {
        return array_values(array_filter($this->children()->all(), static fn (mixed $child): bool => $child instanceof Stat));
    }

    public function grid(): string
    {
        $columns = $this->get(self::Columns);
        $columns = is_int($columns) ? $columns : max(1, min(4, count($this->stats())));

        return WidgetPresets::Grids[$columns] ?? WidgetPresets::Grids[3];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(self::Columns, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'))
            ->withAllowedChildren([Stat::class], 1)
            ->withConstraint(static function (ComponentInterface $group): ?string {
                $names = [];

                foreach ($group->children() as $stat) {
                    if ($stat instanceof Stat) {
                        if (isset($names[$stat->widgetName()])) {
                            return sprintf('two stats share the name "%s".', $stat->widgetName());
                        }

                        $names[$stat->widgetName()] = true;
                    }
                }

                return null;
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }
}
