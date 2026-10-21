<?php

declare(strict_types=1);

namespace Gcego\Widgets\Records;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Widgets\Activity\ActivityFeed;
use Gcego\Widgets\Activity\ActivitySource;
use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * The newest records of a registered blueprint:
 *
 *     new RecentRecords('New students', new Property(RecentRecords::Blueprint, 'students'),
 *         new Property(RecentRecords::TitleColumn, 'name'), new Property(RecentRecords::TimeColumn, 'created_at'),
 *         new Property(RecentRecords::DescriptionColumn, 'level.name'), new Property(RecentRecords::Route, 'students.show'))
 *
 * The blueprint's requirements, Source constraints, tenant scopes and
 * policies apply (through gcego/data); relations are eager-loaded; at most
 * activity.max_limit records.
 */
final class RecentRecords extends ActivityFeed
{
    public const string Blueprint = 'blueprint';

    public const string TitleColumn = 'title_column';

    public const string TimeColumn = 'time_column';

    public const string DescriptionColumn = 'description_column';

    public const string BadgeColumn = 'badge_column';

    public const string Route = 'route';

    /** route parameter => record path (default: the route's first parameter => the record key "id"). */
    public const string RouteParameters = 'route_parameters';

    public function view(): string
    {
        return 'gcego-widgets::activity.feed';
    }

    public function blueprint(): string
    {
        $blueprint = $this->stringProperty(self::Blueprint) ?? '';

        return str_contains($blueprint, ':') ? $blueprint : 'blueprint:'.$blueprint;
    }

    public function source(): ActivitySource
    {
        $parameters = $this->get(self::RouteParameters);

        try {
            return new ActivitySource(
                $this->blueprint(),
                title: $this->stringProperty(self::TitleColumn) ?? 'name',
                time: $this->stringProperty(self::TimeColumn) ?? 'created_at',
                description: $this->stringProperty(self::DescriptionColumn),
                route: $this->stringProperty(self::Route),
                parameters: self::parameters($parameters),
                badge: $this->stringProperty(self::BadgeColumn),
            );
        } catch (InvalidWidgetException $exception) {
            throw InvalidWidgetException::invalid('RecentRecords "'.$this->widgetName().'"', $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private static function parameters(mixed $parameters): array
    {
        $clean = [];

        foreach (is_array($parameters) ? $parameters : [] as $parameter => $path) {
            if (is_string($path)) {
                $clean[(string) $parameter] = $path;
            }
        }

        return $clean;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $path = static fn (mixed $value): bool => is_string($value) && preg_match(ActivitySource::PathPattern, $value) === 1;

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Blueprint, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^(?:blueprint:)?[a-z][a-z0-9._-]{0,127}$/', $value) === 1, ruleDescription: 'a blueprint name'),
            new PropertyDefinition(self::TitleColumn, Type::string(), default: 'name', rule: $path, ruleDescription: 'a record path'),
            new PropertyDefinition(self::TimeColumn, Type::string(), default: 'created_at', rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z_][A-Za-z0-9_]{0,63}$/', $value) === 1, ruleDescription: 'a column'),
            new PropertyDefinition(self::DescriptionColumn, Type::string(), rule: $path, ruleDescription: 'a record path'),
            new PropertyDefinition(self::BadgeColumn, Type::string(), rule: $path, ruleDescription: 'a record path'),
            new PropertyDefinition(self::Route, Type::string()),
            new PropertyDefinition(self::RouteParameters, Type::array(), default: []),
        );
    }
}
