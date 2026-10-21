<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;

/**
 * Shared by Section and Card: a titled region with an optional icon,
 * header actions (an Actions child), a body and a footer (a Footer child).
 * When nothing in the body renders, it hides itself, or shows its Empty
 * text when it has one.
 */
abstract class Panel extends LayoutComponent
{
    public const string Padding = 'padding';

    /** Text shown when nothing in the body renders (instead of hiding). */
    public const string Empty = 'empty';

    /** Styling slot of the empty text. */
    public const string EmptySlot = 'empty';

    public const int DefaultGap = 6;

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', Tailwind::Header => 'The header.', Tailwind::Title => 'The title.', Tailwind::Description => 'The description.', Tailwind::Icon => 'The header icon.', Tailwind::Actions => 'The header actions.', Tailwind::Body => 'The body.', Tailwind::Footer => 'The footer.', self::EmptySlot => 'The empty text.', 'summary' => 'The summary of a collapsible section.'];
    }

    public function ownsRegion(LayoutComponent $child): bool
    {
        return $child instanceof Actions || $child instanceof Footer;
    }

    public function emptyText(): ?string
    {
        return $this->stringProperty(self::Empty);
    }

    public function bodyClasses(): string
    {
        return implode(' ', [...LayoutPresets::Flow, $this->gapClasses(self::DefaultGap)]);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            self::iconProperty(),
            self::choice(self::Padding, array_keys(LayoutPresets::Paddings), 'md'),
            new PropertyDefinition(self::Empty, Type::string()),
        );
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Description];
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class];
    }
}
