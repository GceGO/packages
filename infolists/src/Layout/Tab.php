<?php

declare(strict_types=1);

namespace Gcego\Infolists\Layout;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\Support\Icons;

/**
 * One panel of Tabs: new Tab('Overview', new Text(...), ...).
 */
final class Tab extends LayoutComponent
{
    public const string Icon = 'icon';

    public function view(): string
    {
        return 'gcego-infolists::layouts.tab';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Label, Type::string(), required: true),
            new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
        );
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
