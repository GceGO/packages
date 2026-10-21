<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * The secondary region of a Split or Sidebar, as an <aside> (a
 * complementary landmark, labelled by its Label):
 *
 *     new Aside('Recent activity', new ActivityFeed(…))
 *     new Aside('Filters', new Property(Aside::Sticky, true), …)   // stays in view while the main region scrolls
 */
final class Aside extends LayoutComponent
{
    public const string Sticky = 'sticky';

    public function view(): string
    {
        return 'gcego-layouts::layouts.region';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['element' => 'aside', 'region' => implode(' ', [...LayoutPresets::Region, ...LayoutPresets::Flow, LayoutPresets::Gaps['default'][$layout->config->defaultGap]])];
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }

    public function isSticky(): bool
    {
        return $this->boolProperty(self::Sticky);
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(self::Sticky, Type::bool(), default: false));
    }
}
