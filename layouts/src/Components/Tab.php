<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Runtime\Rendering\RenderContext;
use Illuminate\Support\Str;

/**
 * One tab of a Tabs component: a label and its panel content.
 *
 *     new Tab('Results', new Property(Tab::Icon, 'chart'), new Table(…))
 */
final class Tab extends LayoutComponent
{
    public const string NamePattern = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function view(): string
    {
        return 'gcego-layouts::layouts.region';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['element' => 'div', 'region' => implode(' ', [...LayoutPresets::Flow, LayoutPresets::Gaps['default'][$layout->config->defaultGap]])];
    }

    /**
     * The stable identifier of this tab: its Name, or its Label as a slug.
     */
    public function tabName(): string
    {
        $name = $this->stringProperty(self::Name);

        return $name ?? Str::limit(Str::slug($this->title() ?? 'tab'), 64, '');
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true),
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase identifier such as "results"'),
                self::iconProperty(),
            );
    }
}
