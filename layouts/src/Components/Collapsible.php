<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * A region the user shows and hides (a native <details> disclosure; no
 * JavaScript, keyboard operable, state announced). Closed by default:
 *
 *     new Collapsible('Advanced details', new Infolist(…))
 *     new Collapsible('Notes', new Property(Collapsible::Open, true), …)
 *
 * The chevron rotates with a short transition, disabled for users who
 * prefer reduced motion. The open state survives Livewire updates.
 */
final class Collapsible extends LayoutComponent
{
    public const string Open = 'open';

    public const string SummarySlot = 'summary';

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', self::SummarySlot => 'The summary.', Tailwind::Description => 'The description.', Tailwind::Body => 'The content.'];
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.collapsible';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['open' => $this->boolProperty(self::Open), 'body' => implode(' ', LayoutPresets::CollapsibleBody)];
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Description];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Label, Type::string(), required: true),
            new PropertyDefinition(self::Open, Type::bool(), default: false),
            self::iconProperty(),
        );
    }
}
