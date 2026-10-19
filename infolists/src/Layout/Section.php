<?php

declare(strict_types=1);

namespace Gcego\Infolists\Layout;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\Icons;

/**
 * A titled group of entries:
 *
 *     new Section('Basic Information', new Text('name', 'Name'), new Text('code', 'Code'))
 *     new Section('History', ..., new Property(Section::Collapsible, true), new Property(Section::Collapsed, true))
 *     new Section('Contact', ..., new Property(Section::Icon, 'information-circle'), new Property(Section::Columns, 3))
 *
 * Rendered as <section aria-labelledby> with a heading; collapsible
 * sections use a button with aria-expanded/aria-controls.
 */
final class Section extends LayoutComponent
{
    public const string Icon = 'icon';

    public const string Collapsible = 'collapsible';

    public const string Collapsed = 'collapsed';

    public function view(): string
    {
        return 'gcego-infolists::layouts.section';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
            new PropertyDefinition(self::Collapsible, Type::bool(), default: false),
            new PropertyDefinition(self::Collapsed, Type::bool(), default: false),
        );
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
