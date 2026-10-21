<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * A titled part of a page, as a labelled <section> with a heading at the
 * current level (its children's headings are one level deeper):
 *
 *     new Section('Academic overview', 'This term so far', new Grid(…))
 *     new Section('Students', new Actions(new Create), new Table(…))
 *     new Section('Archive', new Property(Section::Collapsible, true), new Property(Section::Collapsed, true), …)
 *
 * Surface: card (default), muted or none. A collapsible section is a native
 * <details> disclosure (keyboard and screen-reader support built in, no
 * JavaScript); its open state survives Livewire updates.
 */
final class Section extends Panel
{
    public const string Collapsible = 'collapsible';

    public const string Collapsed = 'collapsed';

    public const string Surface = 'surface';

    public function view(): string
    {
        return 'gcego-layouts::layouts.section';
    }

    public function viewData(LayoutView $layout): array
    {
        $surface = $this->stringProperty(self::Surface) ?? 'card';

        return [
            'surface' => trim(LayoutPresets::Surfaces[$surface].' '.($surface === 'none' ? '' : LayoutPresets::Paddings[$this->stringProperty(self::Padding) ?? 'md']).' '.implode(' ', LayoutPresets::SectionPlain)),
            'collapsible' => $this->boolProperty(self::Collapsible) && $this->title() !== null,
            'open' => ! $this->boolProperty(self::Collapsed),
            'body' => $this->bodyClasses(),
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Collapsible, Type::bool(), default: false),
            new PropertyDefinition(self::Collapsed, Type::bool(), default: false),
            self::choice(self::Surface, array_keys(LayoutPresets::Surfaces), 'card'),
        );
    }
}
