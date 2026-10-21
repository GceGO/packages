<?php

declare(strict_types=1);

namespace Gcego\Forms\Schema;

use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use Gcego\Forms\Configuration\DisabledWhen;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\RequiredWhen;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Form;
use Gcego\Forms\Layout\LayoutComponent;
use Gcego\Forms\Layout\Step;
use Gcego\Forms\Layout\Wizard;

/**
 * Compiles a Form into a FormSchema. Pure: no database, no container, no state.
 *
 * Rejects duplicate state paths, conditions and option dependencies on
 * unknown fields, dependency cycles and misplaced wizards.
 */
final class SchemaCompiler
{
    /**
     * @var array<string, list<TreeNode>>
     */
    private array $childrenOf = [];

    /**
     * @var list<FieldEntry>
     */
    private array $entries = [];

    /**
     * @var list<string>
     */
    private array $steps = [];

    public function compile(Form $form): FormSchema
    {
        $this->childrenOf = [];
        $this->entries = [];
        $this->steps = [];
        $root = null;

        foreach (app(ComponentWalker::class)->preOrder($form) as $node) {
            $root ??= $node;

            if ($node->parentPath !== null) {
                $this->childrenOf[$node->parentPath->toString()][] = $node;
            }
        }

        if ($root === null) {
            throw InvalidFieldException::invalid('Form', 'it could not be traversed.');
        }

        $this->walk($root, null, [], [], null);

        $paths = [];

        foreach ($this->entries as $entry) {
            if (isset($paths[$entry->path])) {
                throw InvalidFieldException::invalid(sprintf('Field "%s"', $entry->path), 'two fields share this state path; rename one.');
            }

            $paths[$entry->path] = true;
        }

        [$live, $dependencies] = $this->dependencies($paths);

        return new FormSchema($form, $this->entries, $live, $dependencies, $this->steps);
    }

    /**
     * @param  list<array{condition: VisibleWhen, scope: string|null}>  $visibility
     * @param  list<string>  $siblings
     */
    private function walk(TreeNode $node, ?string $repeater, array $visibility, array $siblings, ?int $step): void
    {
        $children = $this->childrenOf[$node->path->toString()] ?? [];

        if ($repeater === null || $siblings === []) {
            $siblings = $this->fieldNames($children);
        }

        foreach ($children as $child) {
            $component = $child->component;
            $chain = [...$visibility, ...array_map(static fn (VisibleWhen $condition): array => ['condition' => $condition, 'scope' => $repeater], $component->configurations()->ofClass(VisibleWhen::class))];

            if ($component instanceof Wizard) {
                if ($repeater !== null || $step !== null || $this->steps !== []) {
                    throw InvalidFieldException::invalid('Wizard', 'a form may contain one Wizard, outside repeaters.');
                }

                foreach ($this->childrenOf[$child->path->toString()] ?? [] as $index => $stepNode) {
                    $this->steps[] = $stepNode->component instanceof Step ? ($stepNode->component->heading() ?? 'Step '.($index + 1)) : 'Step '.($index + 1);
                    $this->walk($stepNode, $repeater, [...$chain, ...array_map(static fn (VisibleWhen $condition): array => ['condition' => $condition, 'scope' => $repeater], $stepNode->component->configurations()->ofClass(VisibleWhen::class))], $siblings, $index);
                }

                continue;
            }

            if ($component instanceof LayoutComponent) {
                $this->walk($child, $repeater, $chain, $siblings, $step);

                continue;
            }

            if (! $component instanceof Field) {
                continue;
            }

            $path = $repeater === null ? $component->fieldName() : $repeater.'.*.'.$component->fieldName();
            $this->entries[] = new FieldEntry(
                field: $component,
                path: $path,
                componentPath: $child->path->toString(),
                repeater: $repeater,
                visibility: $chain,
                required: $component->configurations()->ofClass(RequiredWhen::class),
                disabled: $component->configurations()->ofClass(DisabledWhen::class),
                siblings: $siblings,
                step: $step,
            );

            if ($component instanceof Repeater) {
                $this->walk($child, $path, $chain, $this->fieldNames($this->descendantFields($child)), $step);
            }
        }
    }

    /**
     * Direct field descendants, looking through layouts (they share the item scope).
     *
     * @return list<TreeNode>
     */
    private function descendantFields(TreeNode $node): array
    {
        $fields = [];

        foreach ($this->childrenOf[$node->path->toString()] ?? [] as $child) {
            if ($child->component instanceof Field) {
                $fields[] = $child;
            } elseif ($child->component instanceof LayoutComponent) {
                array_push($fields, ...$this->descendantFields($child));
            }
        }

        return $fields;
    }

    /**
     * @param  list<TreeNode>  $nodes
     * @return list<string>
     */
    private function fieldNames(array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if ($node->component instanceof Field) {
                $names[] = $node->component->fieldName();
            } elseif ($node->component instanceof LayoutComponent) {
                array_push($names, ...$this->fieldNames($this->childrenOf[$node->path->toString()] ?? []));
            }
        }

        return $names;
    }

    /**
     * @param  array<string, true>  $paths
     * @return array{0: list<string>, 1: array<string, list<string>>}
     */
    private function dependencies(array $paths): array
    {
        $graph = [];

        foreach ($this->entries as $entry) {
            $references = [];

            foreach ($entry->visibility as ['condition' => $condition, 'scope' => $scope]) {
                foreach ($condition->condition->dependencies() as $name) {
                    if ($name === $entry->name() && ($scope === $entry->repeater)) {
                        throw InvalidFieldException::invalid(sprintf('Field "%s"', $entry->path), 'it is only visible when it has a value, which it could never get.');
                    }

                    $references[] = [$name, $scope];
                }
            }

            foreach ([...$entry->required, ...$entry->disabled] as $condition) {
                foreach ($condition->condition->dependencies() as $name) {
                    $references[] = [$name, $entry->repeater];
                }
            }

            foreach ($entry->field->configurations()->ofClass(Options::class) as $options) {
                foreach ($options->dependsOn as $name) {
                    $references[] = [$name, $entry->repeater];
                }
            }

            foreach ($references as [$name, $scope]) {
                $target = $scope !== null && isset($paths[$scope.'.*.'.$name]) ? $scope.'.*.'.$name : $name;

                if (! isset($paths[$target])) {
                    throw InvalidFieldException::invalid(sprintf('Field "%s"', $entry->path), sprintf('it depends on "%s", which is not a field of this form.', $name));
                }

                if ($target !== $entry->path) {
                    $graph[$entry->path][] = $target;
                }
            }
        }

        $graph = array_map(static fn (array $targets): array => array_values(array_unique($targets)), $graph);
        $this->assertAcyclic($graph);
        $live = array_values(array_unique(array_merge(...array_values($graph ?: [[]]))));

        return [$live, $graph];
    }

    /**
     * @param  array<string, list<string>>  $graph
     */
    private function assertAcyclic(array $graph): void
    {
        $state = [];

        foreach (array_keys($graph) as $node) {
            $this->visit($node, [], $state, $graph);
        }
    }

    /**
     * @param  list<string>  $trail
     * @param  array<string, string>  $state
     * @param  array<string, list<string>>  $graph
     */
    private function visit(string $node, array $trail, array &$state, array $graph): void
    {
        if (($state[$node] ?? null) === 'done') {
            return;
        }

        if (($state[$node] ?? null) === 'visiting') {
            throw InvalidFieldException::invalid('Form', sprintf('fields depend on each other in a cycle (%s).', implode(' -> ', [...$trail, $node])));
        }

        $state[$node] = 'visiting';

        foreach ($graph[$node] ?? [] as $next) {
            $this->visit($next, [...$trail, $node], $state, $graph);
        }

        $state[$node] = 'done';
    }
}
