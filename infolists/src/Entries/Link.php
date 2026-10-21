<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Tables\Support\ValueResolver;
use Throwable;

/**
 * A link from a named route or from the value:
 *
 *     new Link('category.name', 'Category', new Property(Link::Route, 'categories.show'), new Property(Link::RouteParameters, ['category' => 'category_id']))
 *     new Link('website', 'Website', new Property(Link::NewTab, true), new Property(Link::Copyable, true))
 *     new Link('email', 'Email', new Property(Link::Mailto, true))
 *
 * Value URLs must be http, https, mailto or root-relative; anything else
 * (javascript:, data:) shows as plain text. New-tab links get
 * rel="noopener noreferrer"; labels are escaped.
 */
class Link extends Entry
{
    public const string Route = 'route';

    public const string RouteParameters = 'route_parameters';

    public const string UrlColumn = 'url_column';

    /** The value is an email address: link it with mailto:. */
    public const string Mailto = 'mailto';

    /** A fixed label instead of the value. */
    public const string Text = 'text';

    public const string NewTab = 'new_tab';

    public const string Icon = 'icon';

    public function view(): string
    {
        return 'gcego-infolists::entries.link';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $display = parent::display($value, $record, $config);
        $href = $this->href($value, $record);
        $newTab = $this->get(self::NewTab) === true;
        $label = $this->stringProperty(self::Text);

        return [
            ...$display,
            'text' => $display['text'] === null ? null : ($label === null ? $display['text'] : Formats::translate($label)),
            'href' => $display['text'] === null ? null : $href,
            'copyValue' => $href ?? $display['text'],
            'target' => $newTab ? '_blank' : null,
            'rel' => $newTab ? 'noopener noreferrer' : null,
            'icon' => $this->stringProperty(self::Icon),
        ];
    }

    public function href(mixed $value, mixed $record): ?string
    {
        $route = $this->stringProperty(self::Route);

        if ($route !== null) {
            $parameters = [];

            foreach ($this->mapProperty(self::RouteParameters) as $parameter => $path) {
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

        if (! is_string($url)) {
            return null;
        }

        if ($this->get(self::Mailto) === true) {
            return filter_var($url, FILTER_VALIDATE_EMAIL) === false ? null : 'mailto:'.$url;
        }

        return SafeUrl::link($url);
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        $relations = parent::relations();

        foreach ([...array_values($this->mapProperty(self::RouteParameters)), $this->stringProperty(self::UrlColumn)] as $path) {
            if (is_string($path) && str_contains($path, '.')) {
                $relations[] = substr($path, 0, (int) strrpos($path, '.'));
            }
        }

        return array_values(array_unique($relations));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $path = static fn (mixed $value): bool => is_string($value) && preg_match(Entry::NamePattern, $value) === 1;

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Route, Type::string()),
            new PropertyDefinition(self::RouteParameters, Type::array(), default: [], rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $item): bool => ! is_string($item) || preg_match(Entry::NamePattern, $item) !== 1) === [], ruleDescription: 'a map of route parameters to record paths'),
            new PropertyDefinition(self::UrlColumn, Type::string(), rule: $path, ruleDescription: 'an attribute name or relation path'),
            new PropertyDefinition(self::Mailto, Type::bool(), default: false),
            new PropertyDefinition(self::Text, Type::string()),
            new PropertyDefinition(self::NewTab, Type::bool(), default: false),
            new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
        );
    }
}
