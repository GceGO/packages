<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Rendering\Context\RenderContext;

/**
 * Expandable items, built on native <details> disclosure elements: every
 * item is a keyboard-operable button (Enter and Space), announced as
 * expanded or collapsed, with no JavaScript.
 *
 *     new Accordion(
 *         new AccordionItem('Fees', new Infolist(…)),
 *         new AccordionItem('Transport', new Property(AccordionItem::Open, true), …),
 *     )
 *
 * Multiple (default true): several items may be open at once. With false,
 * opening one closes the others (the HTML exclusive accordion; browsers
 * without it keep each item independent). Open items stay open across
 * Livewire updates.
 */
final class Accordion extends LayoutComponent
{
    public const string Multiple = 'multiple';

    public const string ItemSlot = 'item';

    public const string SummarySlot = 'summary';

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', self::ItemSlot => 'Each item.', self::SummarySlot => 'Each item summary.'];
    }

    public function ownsRegion(LayoutComponent $child): bool
    {
        return $child instanceof AccordionItem;
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.accordion';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['group' => $this->get(self::Multiple) === false ? $layout->id('group') : null];
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(self::Multiple, Type::bool(), default: true))
            ->withAllowedChildren([AccordionItem::class], 1);
    }
}
