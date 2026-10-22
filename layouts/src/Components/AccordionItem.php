<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Rendering\Context\RenderContext;

/**
 * One item of an Accordion: a summary (Label, Description, Icon) and content.
 */
final class AccordionItem extends LayoutComponent
{
    public const string Open = 'open';

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The item.', Tailwind::Body => 'The item content.'];
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.region';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['element' => 'div', 'region' => implode(' ', LayoutPresets::AccordionBody)];
    }

    public function isOpen(): bool
    {
        return $this->boolProperty(self::Open);
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
