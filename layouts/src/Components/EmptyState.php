<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * A placeholder for content that does not exist yet, with optional actions:
 *
 *     new EmptyState('No students yet', 'Import a class list to get started.', new Property(EmptyState::Icon, 'users'), new Create)
 */
final class EmptyState extends LayoutComponent
{
    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', Tailwind::Icon => 'The icon.', Tailwind::Title => 'The title.', Tailwind::Description => 'The description.', Tailwind::Actions => 'The actions.'];
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.empty-state';
    }

    public function hidesWhenEmpty(): bool
    {
        return false;
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
            self::iconProperty(),
        );
    }
}
