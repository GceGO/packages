<?php

declare(strict_types=1);

namespace Gcego\Layouts\Registry;

use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Layouts\Layout;
use Gcego\Layouts\LayoutComponent;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The "layout" definition kind of gcego/registry: layouts are registered
 * and discovered (app/GCEGo/Layouts) under "layout:<name>". Clients refer to
 * them by that identity only, never by class.
 *
 * A registered layout has no data source of its own, so blueprint parts
 * that need one (tables, forms, infolists, actions) are rejected here: put
 * them in a Blueprint (which may contain layouts) instead. Widgets, layout
 * components and static components are fine.
 */
final readonly class LayoutKind implements DefinitionKind
{
    public const string Id = 'layout';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Layout::class;
    }

    public function name(object $definition): string
    {
        return $this->layout($definition)->layoutName();
    }

    public function validate(object $definition): array
    {
        $layout = $this->layout($definition);
        $errors = array_map(strval(...), $layout->validate()->errors());

        if ($layout->layoutName() === '' || preg_match(Layout::NamePattern, $layout->layoutName()) !== 1) {
            $errors[] = 'A layout needs a Name such as "dashboard" (new Layout(\'dashboard\', …)).';
        }

        return [...$errors, ...self::blueprintOnlyParts($layout)];
    }

    public function metadata(object $definition): array
    {
        $layout = $this->layout($definition);
        $nodes = 0;

        foreach (app(ComponentWalker::class)->preOrder($layout) as $node) {
            $nodes++;
        }

        return ['title' => $layout->title(), 'description' => $layout->description(), 'components' => $nodes];
    }

    /**
     * Blueprint parts that need a blueprint's Source, anywhere in a layout.
     *
     * @return list<string>
     */
    public static function blueprintOnlyParts(LayoutComponent $layout): array
    {
        $errors = [];

        foreach (app(ComponentWalker::class)->preOrder($layout) as $node) {
            if ($node->component instanceof BlueprintPart && ! $node->component instanceof LayoutComponent) {
                $errors[] = sprintf('%s at "%s" needs a blueprint (its Source and actions); put this layout in a Blueprint instead of registering it on its own.', class_basename($node->component), $node->path->toString());
            }
        }

        return $errors;
    }

    private function layout(object $definition): Layout
    {
        return $definition instanceof Layout ? $definition : throw new InvalidArgumentException(sprintf('%s is not a layout.', $definition::class));
    }
}
