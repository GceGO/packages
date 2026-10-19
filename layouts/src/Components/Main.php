<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\ComponentDefinition;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * The main region of a Split or Sidebar. Element: div (default), main
 * (only when the page has no other <main>), section or article; a section
 * or article with a Label is labelled by it.
 */
final class Main extends LayoutComponent
{
    public const string Element = 'element';

    public function view(): string
    {
        return 'gcego-layouts::layouts.region';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['element' => $this->element(), 'region' => implode(' ', [...LayoutPresets::Region, ...LayoutPresets::Flow, LayoutPresets::Gaps['default'][$layout->config->defaultGap]])];
    }

    public function element(): string
    {
        return $this->stringProperty(self::Element) ?? 'div';
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(self::choice(self::Element, ['div', 'main', 'section', 'article'], 'div'));
    }
}
