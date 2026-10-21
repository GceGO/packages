<?php

declare(strict_types=1);

namespace Gcego\Pages\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Pages\Contracts\PageComponent;
use Gcego\Pages\Page;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Support\Icons;

/**
 * A link to another registered page, styled as a button. It is shown only
 * when that page has a route and the user may open it (for a record page:
 * may open it for this record), so a link never leads to a 403:
 *
 *     new PageLink('New subject', 'subjects.create')                     // in an index page header
 *     new PageLink('Edit', 'subjects.edit')                              // on a view page: same record
 *     new PageLink('Members', 'institutions.members', new Property(PageLink::Parameters, ['institution' => ':tenant']))
 *     new PageLink('Topics', 'subjects.topics', new Property(PageLink::Parameters, ['subject' => ':record']))   // on a record page
 *
 * Direct children of a page appear in the page header; elsewhere (in a
 * section, an empty state) the link renders in place. A link is navigation,
 * not an action: it never changes anything.
 */
final class PageLink extends Component implements PageComponent
{
    /** The target page name ("subjects.edit"). */
    public const string Page = 'page';

    public const string Icon = 'icon';

    /** primary or secondary. */
    public const string Intent = 'intent';

    /** Extra route parameters of the target: scalars, ":tenant" for the current tenant key, or ":record" for the current record's key. */
    public const string Parameters = 'parameters';

    /** Pass the current record to a record page (default: true when the target needs one). */
    public const string Record = 'record';

    /**
     * @return array<string, string> Slot => description.
     */
    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The link.', Tailwind::Icon => 'Its icon.'];
    }

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : '';
    }

    public function target(): string
    {
        $page = $this->get(self::Page);

        return is_string($page) ? $page : '';
    }

    public function icon(): ?string
    {
        $icon = $this->get(self::Icon);

        return is_string($icon) ? $icon : null;
    }

    public function isPrimary(): bool
    {
        return $this->get(self::Intent) === 'primary';
    }

    /**
     * @return array<string, string|int>
     */
    public function parameters(): array
    {
        $parameters = $this->get(self::Parameters);
        $values = [];

        foreach (is_array($parameters) ? $parameters : [] as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    public function passesRecord(): ?bool
    {
        $record = $this->get(self::Record);

        return is_bool($record) ? $record : null;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('pages.link')
            ->withoutProperties(self::Description, self::Id)
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120, ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::Page, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 100 && preg_match(Page::NamePattern, $value) === 1, ruleDescription: 'a page name such as "subjects.create"'),
                new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Intent, Type::string(), default: 'secondary', allowedValues: ['primary', 'secondary']),
                new PropertyDefinition(self::Parameters, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $parameter, mixed $name): bool => ! is_string($name) || preg_match(Page::ParameterPattern, $name) !== 1 || ! (is_int($parameter) || (is_string($parameter) && strlen($parameter) <= 191)), ARRAY_FILTER_USE_BOTH) === [], ruleDescription: 'route parameters keyed by name, with string or integer values'),
                new PropertyDefinition(self::Record, Type::bool()),
            )
            ->withoutChildren()
            ->withAllowedConfigurations([Requirement::class, Tailwind::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Page];
    }
}
