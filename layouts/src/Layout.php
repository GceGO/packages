<?php

declare(strict_types=1);

namespace Gcego\Layouts;

use Gcego\Chroma\Tailwind;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Type;
use Gcego\Layouts\Components\Accordion;
use Gcego\Layouts\Components\AccordionItem;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Sidebar;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Illuminate\Support\Str;

/**
 * A complete layout: its children in a vertical flow, optionally under a
 * title. Register it (app/GCEGo/Layouts, or Registry::register()) to render
 * it by identity ("layout:dashboard"), or place it in a Blueprint.
 *
 *     new Layout('dashboard', new Permission('dashboard.view'),
 *         new Section('Overview', new Grid(new Columns(3), new Stat(…), new Stat(…), new Stat(…))),
 *         new Section('Enrolment trends', new LineChart(…)),
 *     )
 *
 * Validation also checks regions are placed where they belong: Main and
 * Aside in a Split or Sidebar, Tab in Tabs, AccordionItem in an Accordion.
 */
final class Layout extends LayoutComponent
{
    public const string NamePattern = '/^[a-z][a-z0-9_-]{0,63}$/';

    /** @var array<class-string<LayoutComponent>, list<class-string<LayoutComponent>>> */
    private const array Parents = [
        Main::class => [Split::class, Sidebar::class],
        Aside::class => [Split::class, Sidebar::class],
        Tab::class => [Tabs::class],
        AccordionItem::class => [Accordion::class],
    ];

    /**
     * The registry name: the Name, or the Label in snake case.
     */
    public function layoutName(): string
    {
        $name = $this->stringProperty(self::Name);

        if ($name !== null) {
            return $name;
        }

        $label = $this->stringProperty(self::Label);

        return $label === null ? '' : mb_substr(Str::snake(Str::ascii(trim((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', $label)))), 0, 64);
    }

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', Tailwind::Header => 'The title area.', Tailwind::Title => 'The title.', Tailwind::Description => 'The description.'];
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.layout';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['flow' => implode(' ', LayoutPresets::Flow).' '.$this->gapClasses($layout->config->defaultGap)];
    }

    /**
     * Misplaced regions anywhere in a component tree.
     *
     * @return list<string>
     */
    public static function misplacedRegions(ComponentInterface $root): array
    {
        $components = [];
        $errors = [];

        foreach ((new ComponentWalker)->preOrder($root) as $node) {
            $components[$node->path->toString()] = $node->component;
            $parent = $node->parentPath === null ? null : ($components[$node->parentPath->toString()] ?? null);

            foreach (self::Parents as $region => $parents) {
                if ($node->component instanceof $region && ($parent === null || ! in_array($parent::class, $parents, true))) {
                    $errors[] = sprintf('%s at "%s" must be a direct child of %s.', class_basename($region), $node->path->toString(), implode(' or ', array_map(class_basename(...), $parents)));
                }
            }
        }

        return $errors;
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase name such as "dashboard"'))
            ->withConstraint(static fn (ComponentInterface $layout): array => self::misplacedRegions($layout));
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class];
    }
}
