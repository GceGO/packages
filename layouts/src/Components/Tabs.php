<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Runtime\Rendering\RenderContext;
use Illuminate\Support\Js;

/**
 * Tabbed panels, following the WAI-ARIA tabs pattern (automatic activation):
 *
 *     new Tabs(
 *         new Tab('Profile', new Infolist(…)),
 *         new Tab('Results', new Table(…)),
 *     )
 *
 * Arrow keys move between tabs (Home and End to the first and last), Tab
 * moves into the panel. Every panel is rendered on the server and the first
 * (or Active) one is shown; Alpine.js (bundled with Livewire) switches
 * panels in the browser without requests, and the choice survives Livewire
 * updates of the surrounding component. Tabs whose content the user may not
 * see are left out. Each tab's id derives from its Name (or Label), so it is
 * stable across renders.
 */
final class Tabs extends LayoutComponent
{
    /** The name of the tab shown first (default: the first visible tab). */
    public const string Active = 'active';

    public const string TabListSlot = 'tablist';

    public const string TabSlot = 'tab';

    public const string PanelSlot = 'panel';

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', self::TabListSlot => 'The tab list.', self::TabSlot => 'Each tab.', self::PanelSlot => 'Each panel.'];
    }

    public function ownsRegion(LayoutComponent $child): bool
    {
        return $child instanceof Tab;
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.tabs';
    }

    public function viewData(LayoutView $layout): array
    {
        $names = array_map(static fn (LayoutView $tab): string => $tab->component instanceof Tab ? $tab->component->tabName() : '', $layout->regions);
        $active = $this->stringProperty(self::Active);
        $active = in_array($active, $names, true) ? $active : ($names[0] ?? '');

        return [
            'names' => $names,
            'active' => $active,
            'alpine' => self::alpine($active, $names),
        ];
    }

    /**
     * The Alpine.js state of a tab set: one self-contained expression (no
     * global script), so it also works in HTML that Livewire morphs in.
     *
     * @param  list<string>  $names
     */
    public static function alpine(string $active, array $names): string
    {
        return '{ active: '.Js::from($active).', tabs: '.Js::from($names).', '
            .'select(name) { this.active = name }, '
            .'move(step) { this.focus((this.tabs.indexOf(this.active) + step + this.tabs.length) % this.tabs.length) }, '
            .'focus(index) { const name = this.tabs.at(index); if (name === undefined) { return } this.active = name; this.$nextTick(() => this.$refs[\'tab-\' + name]?.focus()) } }';
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(self::Active, Type::string()))
            ->withAllowedChildren([Tab::class], 1)
            ->withConstraint(static function (ComponentInterface $tabs): array {
                $names = array_map(static fn (ComponentInterface $tab): string => $tab instanceof Tab ? $tab->tabName() : '', $tabs->children()->all());
                $errors = [];

                foreach (array_count_values($names) as $name => $count) {
                    if ($count > 1) {
                        $errors[] = sprintf('two tabs are named "%s"; give one a different Name.', $name);
                    }
                }

                $active = $tabs->get(self::Active);

                if (is_string($active) && ! in_array($active, $names, true)) {
                    $errors[] = sprintf('Active is "%s", which is not one of its tabs (%s).', $active, implode(', ', $names));
                }

                return $errors;
            });
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }
}
