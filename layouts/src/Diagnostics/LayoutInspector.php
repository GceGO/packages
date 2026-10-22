<?php

declare(strict_types=1);

namespace Gcego\Layouts\Diagnostics;

use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Divider;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Row;
use Gcego\Layouts\Components\ScrollArea;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Layout;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutManager;
use Gcego\Layouts\LayoutsConfig;
use Gcego\Layouts\Registry\LayoutKind;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Responsive\Visibility;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Widgets\Widget;
use Illuminate\Contracts\Container\Container;
use Stringable;
use Throwable;

/**
 * Inspects a layout without rendering it or running anything in it (no
 * queries, providers or authorization decisions): its tree, and findings
 * by check.
 *
 * Checks: schema (validation), registry, authorization (a registered layout
 * nobody may see), nesting (misplaced regions, blueprint-only parts in a
 * standalone layout), spans, visibility, identity (duplicate sibling names),
 * renderer, styling (unknown slots), accessibility and performance.
 */
final readonly class LayoutInspector
{
    public function __construct(private Container $container, private LayoutsConfig $config) {}

    public function inspect(string|LayoutComponent $layout): LayoutReport
    {
        $identity = null;

        if (is_string($layout)) {
            try {
                $identity = $this->container->make(LayoutManager::class)->identity($layout);
                $layout = $this->container->make(LayoutManager::class)->resolve($identity);
            } catch (Throwable) {
                return new LayoutReport($identity, [], [new LayoutDiagnostic('registry', LayoutDiagnostic::Error, sprintf('"%s" is not a registered layout.', $layout))]);
            }
        }

        /** @var list<TreeNode> $tree */
        $tree = iterator_to_array(app(ComponentWalker::class)->preOrder($layout), false);
        $parents = [];

        foreach ($tree as $node) {
            $parents[$node->path->toString()] = $node->component;
        }

        $diagnostics = [
            ...$this->schema($layout),
            ...$this->authorization($layout, $identity),
            ...$this->nesting($layout, $identity),
        ];
        $nodes = [];
        $landmarks = ['aside' => 0, 'main' => 0];
        $interactive = 0;
        $depth = 0;

        foreach ($tree as $node) {
            $component = $node->component;
            $path = $node->path->toString();
            $parent = $node->parentPath === null ? null : ($parents[$node->parentPath->toString()] ?? null);
            $depth = max($depth, $node->depth);
            $slots = $this->slots($component);
            $nodes[] = ['path' => $path, 'type' => $component->type(), 'class' => $component::class, 'depth' => $node->depth, 'children' => count($component->children()), 'slots' => $slots];

            array_push($diagnostics, ...$this->placement($component, $parent, $path));
            array_push($diagnostics, ...$this->renderer($component, $path));
            array_push($diagnostics, ...$this->styling($component, $slots, $path));
            array_push($diagnostics, ...$this->accessibility($component, $parent, $path, $landmarks));
            array_push($diagnostics, ...$this->identity($component, $path));

            if (($component instanceof BlueprintPart && ! $component instanceof LayoutComponent) || ($component instanceof Widget && $component->needsLivewire())) {
                $interactive++;
            }
        }

        if (count($tree) > $this->config->maxNodes) {
            $diagnostics[] = new LayoutDiagnostic('performance', LayoutDiagnostic::Warning, sprintf('%d components (more than %d): consider splitting the page or lazy-loading parts of it.', count($tree), $this->config->maxNodes));
        }

        if ($depth > $this->config->maxDepth) {
            $diagnostics[] = new LayoutDiagnostic('performance', LayoutDiagnostic::Warning, sprintf('Nested %d levels deep (more than %d): flatten wrappers that add no structure.', $depth, $this->config->maxDepth));
        }

        if ($interactive > $this->config->maxLivewireComponents) {
            $diagnostics[] = new LayoutDiagnostic('performance', LayoutDiagnostic::Warning, sprintf('%d components mount their own Livewire host (more than %d): each is a separate request on updates.', $interactive, $this->config->maxLivewireComponents));
        }

        return new LayoutReport($identity, $nodes, $diagnostics);
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    private function schema(LayoutComponent $layout): array
    {
        try {
            $errors = $layout->validate()->errors();
        } catch (Throwable $exception) {
            return [new LayoutDiagnostic('schema', LayoutDiagnostic::Error, 'The layout could not be validated: '.$exception->getMessage())];
        }

        $diagnostics = array_map(static fn (object $error): LayoutDiagnostic => new LayoutDiagnostic('schema', LayoutDiagnostic::Error, $error instanceof Stringable ? (string) $error : 'invalid'), $errors);

        if (! $layout instanceof Layout) {
            foreach (Layout::misplacedRegions($layout) as $message) {
                $diagnostics[] = new LayoutDiagnostic('nesting', LayoutDiagnostic::Error, $message);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    private function authorization(LayoutComponent $layout, ?string $identity): array
    {
        if (! $layout instanceof Layout || $layout->requirements() !== []) {
            return [];
        }

        return [new LayoutDiagnostic('authorization', $identity === null ? LayoutDiagnostic::Warning : LayoutDiagnostic::Error, 'The layout has no requirement, so rendering it by identity is denied for everyone (deny by default). Add one, for example new Permission(\'dashboard.view\').')];
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    private function nesting(LayoutComponent $layout, ?string $identity): array
    {
        if (! $layout instanceof Layout) {
            return [];
        }

        return array_map(static fn (string $message): LayoutDiagnostic => new LayoutDiagnostic('nesting', $identity === null ? LayoutDiagnostic::Warning : LayoutDiagnostic::Error, $message), LayoutKind::blueprintOnlyParts($layout));
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    private function placement(ComponentInterface $component, ?ComponentInterface $parent, string $path): array
    {
        $diagnostics = [];

        if ($component->configurations()->first(ColumnSpan::class) !== null && ! $parent instanceof Grid) {
            $diagnostics[] = new LayoutDiagnostic('spans', LayoutDiagnostic::Warning, 'A ColumnSpan only applies to children of a Grid; it is ignored here.', $path);
        }

        if ($component->configurations()->first(ColumnSpan::class) !== null && $parent instanceof Grid && $parent->get(Grid::MinItemWidth) !== null) {
            $diagnostics[] = new LayoutDiagnostic('spans', LayoutDiagnostic::Warning, 'An auto-fit grid (MinItemWidth) ignores spans other than "full".', $path);
        }

        if ($component->configurations()->first(Visibility::class) !== null && ! $parent instanceof LayoutComponent) {
            $diagnostics[] = new LayoutDiagnostic('visibility', LayoutDiagnostic::Warning, 'Visibility only applies to children of a layout component; it is ignored here.', $path);
        }

        return $diagnostics;
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    private function renderer(ComponentInterface $component, string $path): array
    {
        return $this->container->make(RendererRegistry::class)->has($component)
            ? []
            : [new LayoutDiagnostic('renderer', LayoutDiagnostic::Error, sprintf('No renderer is registered for %s (is its package installed?).', $component::class), $path)];
    }

    /**
     * @return list<string>
     */
    private function slots(ComponentInterface $component): array
    {
        return array_keys($this->container->make(SlotRegistry::class)->for($component) ?? []);
    }

    /**
     * @param  list<string>  $slots
     * @return list<LayoutDiagnostic>
     */
    private function styling(ComponentInterface $component, array $slots, string $path): array
    {
        $diagnostics = [];

        foreach ($component->configurations()->ofClass(Tailwind::class) as $tailwind) {
            foreach ($tailwind->slotNames() as $slot) {
                if (! in_array($slot, $slots, true)) {
                    $diagnostics[] = new LayoutDiagnostic('styling', LayoutDiagnostic::Error, sprintf('Slot "%s" is not a slot of %s (slots: %s).', $slot, class_basename($component), implode(', ', $slots)), $path);
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array{aside: int, main: int}  $landmarks
     * @return list<LayoutDiagnostic>
     */
    private function accessibility(ComponentInterface $component, ?ComponentInterface $parent, string $path, array &$landmarks): array
    {
        $diagnostics = [];

        if ($component instanceof ScrollArea && $component->title() === null) {
            $diagnostics[] = new LayoutDiagnostic('accessibility', LayoutDiagnostic::Warning, 'Give the scroll area a Label: screen readers announce it as a region by that name.', $path);
        }

        if ($component instanceof Tabs && count($component->children()) === 1) {
            $diagnostics[] = new LayoutDiagnostic('accessibility', LayoutDiagnostic::Warning, 'A single tab adds a control with nothing to switch to; use a Section.', $path);
        }

        if ($component instanceof Aside && ++$landmarks['aside'] > 1 && $component->title() === null) {
            $diagnostics[] = new LayoutDiagnostic('accessibility', LayoutDiagnostic::Warning, 'Several asides: give each a Label so screen-reader users can tell the landmarks apart.', $path);
        }

        if ($component instanceof Main && $component->element() === 'main' && ++$landmarks['main'] > 1) {
            $diagnostics[] = new LayoutDiagnostic('accessibility', LayoutDiagnostic::Error, 'More than one Main renders as <main>; a page has one main landmark.', $path);
        }

        if ($component instanceof Grid && $component->get(Grid::Dense) === true) {
            $diagnostics[] = new LayoutDiagnostic('accessibility', LayoutDiagnostic::Warning, 'A dense grid can show children out of source order; keyboard and screen-reader order stays the source order.', $path);
        }

        if ($component instanceof Divider && $component->get(Divider::Orientation) === 'vertical' && ! $parent instanceof Row) {
            $diagnostics[] = new LayoutDiagnostic('accessibility', LayoutDiagnostic::Warning, 'A vertical divider needs a Row around it to have height.', $path);
        }

        return $diagnostics;
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    private function identity(ComponentInterface $component, string $path): array
    {
        $names = [];

        foreach ($component->children() as $child) {
            $name = $child->get(Component::Name);

            if (is_string($name)) {
                $names[] = $name;
            }
        }

        $diagnostics = [];

        foreach (array_count_values($names) as $name => $count) {
            if ($count > 1) {
                $diagnostics[] = new LayoutDiagnostic('identity', LayoutDiagnostic::Warning, sprintf('%d children are named "%s": their paths (and Livewire keys, DOM ids) fall back to positions, which change when children are added. Give each a unique Name.', $count, $name), $path);
            }
        }

        return $diagnostics;
    }
}
