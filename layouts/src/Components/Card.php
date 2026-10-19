<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\ComponentDefinition;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * A card: a bordered surface with an optional header (title, description,
 * icon, actions), a body and a footer. Without a title it is a plain
 * container (a <div>); with one, a labelled <section>.
 *
 *     new Card('Enrolment', new Actions(new Action('export', 'Export')), new LineChart(…), new Footer(…))
 *     new Card(new Property(Card::Variant, 'warning'), …)
 *
 * Variants: default, muted, outline, elevated, info, success, warning, danger
 * (the accent is a border, never the only signal: say what it means in the title).
 */
final class Card extends Panel
{
    public const string Variant = 'variant';

    public function view(): string
    {
        return 'gcego-layouts::layouts.card';
    }

    public function viewData(LayoutView $layout): array
    {
        return [
            'card' => trim(LayoutPresets::CardVariants[$this->stringProperty(self::Variant) ?? 'default'].' '.LayoutPresets::Paddings[$this->stringProperty(self::Padding) ?? 'md'].' '.implode(' ', LayoutPresets::SectionPlain)),
            'body' => $this->bodyClasses(),
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(self::choice(self::Variant, array_keys(LayoutPresets::CardVariants), 'default'));
    }
}
