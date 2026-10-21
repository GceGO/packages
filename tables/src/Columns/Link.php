<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;
use Throwable;

/**
 * A link whose URL is generated from a named route, or taken from the value:
 *
 *     new Link('name', 'Subject', new Property(Link::Route, 'subjects.show'), new Property(Link::RouteParameters, ['subject' => 'id']))
 *     new Link('website', 'Website', new Property(Link::NewTab, true))      // the value is the URL
 *
 * Value URLs must be http, https or mailto; anything else ("javascript:",
 * "data:") is shown as plain text. New-tab links get rel="noopener noreferrer".
 */
class Link extends Column
{
    public const string Route = 'route';

    /** route parameter => column or relation path of the row */
    public const string RouteParameters = 'route_parameters';

    /** A column whose value is the URL (default: the column's own value). */
    public const string UrlColumn = 'url_column';

    public const string NewTab = 'new_tab';

    public function cellView(): string
    {
        return 'gcego-tables::columns.link';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $newTab = $this->get(self::NewTab) === true;

        return [
            ...parent::cellData($value, $record, $config),
            'href' => $this->href($value, $record),
            'target' => $newTab ? '_blank' : null,
            'rel' => $newTab ? 'noopener noreferrer' : null,
        ];
    }

    public function href(mixed $value, mixed $record): ?string
    {
        $route = $this->stringProperty(self::Route);

        if ($route !== null) {
            $parameters = [];

            foreach ($this->mapProperty(self::RouteParameters) as $parameter => $path) {
                $parameter = (string) $parameter;
                $resolved = is_string($path) ? ValueResolver::get($record, $path) : null;

                if (! is_scalar($resolved) || $resolved === '') {
                    return null;
                }

                $parameters[$parameter] = $resolved;
            }

            try {
                return route($route, $parameters);
            } catch (Throwable) {
                return null;
            }
        }

        $urlColumn = $this->stringProperty(self::UrlColumn);
        $url = $urlColumn === null ? $value : ValueResolver::get($record, $urlColumn);

        return is_string($url) ? SafeUrl::link($url) : null;
    }

    public function relations(): array
    {
        $paths = [...array_values(array_filter($this->mapProperty(self::RouteParameters), is_string(...))), $this->stringProperty(self::UrlColumn)];
        $relations = parent::relations();

        foreach ($paths as $path) {
            if (is_string($path) && str_contains($path, '.')) {
                $relations[] = substr($path, 0, (int) strrpos($path, '.'));
            }
        }

        return array_values(array_unique($relations));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $path = static fn (mixed $value): bool => is_string($value) && preg_match(Column::NamePattern, $value) === 1;

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Route, Type::string()),
            new PropertyDefinition(self::RouteParameters, Type::array(), default: [], rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $item): bool => ! is_string($item) || preg_match(Column::NamePattern, $item) !== 1) === [], ruleDescription: 'a map of route parameters to column paths'),
            new PropertyDefinition(self::UrlColumn, Type::string(), rule: $path, ruleDescription: 'a column name or relation path'),
            new PropertyDefinition(self::NewTab, Type::bool(), default: false),
        );
    }
}
