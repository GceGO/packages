<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Luminaries\IconName;

/**
 * A titled group of fields (a <section> labelled by its heading), with an optional description and icon:
 *
 *     new Section('Basic Information', new TextInput('name', 'Subject Name'), new Textarea('description', 'Description'))
 *     new Section('Guardian', new Property(Section::Icon, 'heroicon:users'), new Property(Section::Description, '…'), …)
 */
final class Section extends LayoutComponent
{
    public const string Icon = 'icon';

    public function view(): string
    {
        return 'gcego-forms::layouts.section';
    }

    public function icon(): ?string
    {
        $icon = $this->get(self::Icon);

        return is_string($icon) ? $icon : null;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(self::Icon, Type::string(), rule: IconName::accepts(...), ruleDescription: 'an icon name'));
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
