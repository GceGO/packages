<?php

declare(strict_types=1);

namespace Gcego\Components\Live;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\HoldsState;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Evaluation\Evaluator;
use Gcego\Components\Evaluation\State;
use Gcego\Components\Facades\Components;
use Gcego\Components\Support\Presentation;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use Illuminate\Support\Arr;

/**
 * An immutable schema brought to life over mutable state.
 *
 *     $live = Components::live($schema, ['type' => 'person'], ['operation' => 'create']);
 *
 *     $live->isLive('type');               // true: some closure reads it, no ->live() needed
 *     $changes = $live->set('type', 'business');
 *     $changes->components;                // exactly the components to redraw
 *     $live->dehydrate();                  // visible, dehydrated field values only
 *     $live->toArray();                    // a snapshot any frontend can render
 *
 * Construction fills missing field defaults and resolves the tree. Every
 * closure runs while the state records what it reads, so the dependency
 * graph is discovered rather than declared.
 */
final class LiveSchema
{
    private ResolvedComponent $root;

    /**
     * @var array<string, ResolvedComponent>
     */
    private array $index = [];

    /**
     * Visible state holders by state path.
     *
     * @var array<string, list<ResolvedComponent>>
     */
    private array $fields = [];

    /**
     * Component tree paths by the state path they read.
     *
     * @var array<string, list<string>>
     */
    private array $readers = [];

    public function __construct(
        private readonly ComponentInterface $schema,
        private readonly State $state,
        private readonly EvaluationContext $context,
        private readonly ComponentWalker $walker,
        private readonly Evaluator $evaluator,
    ) {
        Components::getFacadeRoot();

        $this->fill($this->walker->rootNode($this->schema), '');
        $this->refresh();
    }

    public function schema(): ComponentInterface
    {
        return $this->schema;
    }

    public function state(): State
    {
        return $this->state;
    }

    public function root(): ResolvedComponent
    {
        return $this->root;
    }

    /**
     * The resolved component at a tree path ("#0.account.email").
     */
    public function at(string $componentPath): ?ResolvedComponent
    {
        return $this->index[$componentPath] ?? null;
    }

    /**
     * The first visible state holder at a state path ("address.city").
     */
    public function field(string $statePath): ?ResolvedComponent
    {
        return $this->fields[$statePath][0] ?? null;
    }

    /**
     * Whether changing this state path can change the schema: a field
     * declared live(), or a value some closure reads (or a parent or child
     * path of one).
     */
    public function isLive(string $statePath): bool
    {
        foreach ($this->fields[$statePath] ?? [] as $field) {
            if ($field->get('live') === true) {
                return true;
            }
        }

        return $this->dependentsOf($statePath) !== [];
    }

    /**
     * State paths of the visible fields that are live.
     *
     * @return list<string>
     */
    public function livePaths(): array
    {
        return array_values(array_filter(array_map(strval(...), array_keys($this->fields)), $this->isLive(...)));
    }

    /**
     * Tree paths of the components whose closures read this state path.
     *
     * @return list<string>
     */
    public function dependentsOf(string $statePath): array
    {
        $dependents = [];

        foreach ($this->readers as $read => $components) {
            $read = (string) $read;

            if ($read === $statePath || str_starts_with($read, $statePath.'.') || str_starts_with($statePath, $read.'.')) {
                array_push($dependents, ...$components);
            }
        }

        return array_values(array_unique($dependents));
    }

    /**
     * Update one value as a user would: write it, run the afterStateUpdated
     * hooks of the visible fields at that path (with $state and $old), then
     * resolve again and report what changed.
     */
    public function set(string $statePath, mixed $value): Changes
    {
        $before = $this->fingerprints();
        $old = $this->state->peek($statePath);

        $recording = $this->state->record(function () use ($statePath, $value, $old): void {
            $this->state->set($statePath, $value);

            foreach ($this->fields[$statePath] ?? [] as $field) {
                $hook = $field->component instanceof Component ? $field->component->get('afterStateUpdated') : null;

                if ($hook instanceof Computed) {
                    $this->evaluator->evaluate(
                        $hook,
                        $this->contextFor($field->containerPath)->with('state', $value)->with('old', $old),
                        $field->component,
                        sprintf('afterStateUpdated on component "%s" (%s)', $field->type(), $field->path),
                    );
                }
            }
        });

        $this->refresh();
        $after = $this->fingerprints();
        $changed = [];

        foreach (array_keys($before + $after) as $path) {
            if (($before[$path] ?? null) !== ($after[$path] ?? null)) {
                $changed[] = (string) $path;
            }
        }

        return new Changes(array_values(array_unique([$statePath, ...$recording['writes']])), $changed);
    }

    /**
     * Write several values, merging what changed.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): Changes
    {
        $statePaths = [];
        $components = [];

        foreach ($values as $path => $value) {
            $changes = $this->set($path, $value);
            array_push($statePaths, ...$changes->statePaths);
            array_push($components, ...$changes->components);
        }

        $order = array_flip(array_keys($this->index));
        $components = array_values(array_unique($components));
        usort($components, static fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return new Changes(array_values(array_unique($statePaths)), $components);
    }

    /**
     * The values to submit: visible state holders that are not
     * dehydrated(false), nested by state path. Hidden fields, and fields in
     * hidden containers, are never included.
     *
     * @return array<array-key, mixed>
     */
    public function dehydrate(): array
    {
        $data = [];

        foreach ($this->index as $component) {
            if ($component->holdsState() && ! $component->hidden && $component->get('dehydrated') !== false && $component->statePath !== null) {
                Arr::set($data, $component->statePath, $this->state->peek($component->statePath));
            }
        }

        return $data;
    }

    /**
     * A renderer-neutral snapshot: the state and the resolved tree with
     * normalized properties, live flags and dependencies.
     *
     * @return array{state: array<array-key, mixed>, components: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['state' => $this->state->all(), 'components' => $this->export($this->root)];
    }

    /**
     * @return array<string, mixed>
     */
    private function export(ResolvedComponent $component): array
    {
        return [
            'type' => $component->type(),
            'path' => $component->path,
            'name' => $component->name(),
            'label' => $component->label,
            'statePath' => $component->statePath,
            'hidden' => $component->hidden,
            'live' => $component->holdsState() && $component->statePath !== null && $this->isLive($component->statePath),
            'dependsOn' => $component->dependencies,
            'properties' => Components::normalizeValue($component->properties, $component->path),
            'children' => array_map($this->export(...), $component->children),
        ];
    }

    private function refresh(): void
    {
        $this->fields = [];
        $this->readers = [];
        $this->root = $this->resolve($this->walker->rootNode($this->schema), '');
        $this->index = self::preOrder($this->root);
    }

    private function fill(TreeNode $node, string $containerPath): void
    {
        [$statePath, $childContainer] = $this->paths($node->component, $containerPath);
        $component = $node->component;

        if ($statePath !== null && $component instanceof Component && ! $this->state->has($statePath)) {
            $this->state->set($statePath, $component->evaluateProperty('default', $this->contextFor($containerPath)));
        }

        foreach ($this->walker->childNodes($node) as $child) {
            $this->fill($child, $childContainer);
        }
    }

    private function resolve(TreeNode $node, string $containerPath): ResolvedComponent
    {
        $component = $node->component;
        [$statePath, $childContainer] = $this->paths($component, $containerPath);
        $context = $this->contextFor($containerPath);

        if ($statePath !== null) {
            $context = $context->with('state', $this->state->peek($statePath));
        }

        $recording = $this->state->record(fn (): array => $this->evaluate($component, $context));
        $properties = $recording['result'];
        $hidden = Presentation::isHidden($properties, $context->operation());
        $path = $node->path->toString();
        $children = [];

        if (! $hidden) {
            foreach ($this->walker->childNodes($node) as $child) {
                $children[] = $this->resolve($child, $childContainer);
            }
        }

        $name = $properties['name'] ?? null;
        $resolved = new ResolvedComponent(
            component: $component,
            path: $path,
            containerPath: $containerPath,
            statePath: $statePath,
            properties: $properties,
            hidden: $hidden,
            label: Presentation::label($properties['label'] ?? null, is_string($name) ? $name : null),
            dependencies: array_values(array_diff($recording['reads'], [$statePath ?? ''])),
            children: $children,
        );

        foreach ($resolved->dependencies as $read) {
            $this->readers[$read][] = $path;
        }

        if ($statePath !== null && ! $hidden) {
            $this->fields[$statePath][] = $resolved;
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluate(ComponentInterface $component, EvaluationContext $context): array
    {
        if (! $component instanceof Component) {
            return [];
        }

        $definition = $component::definition();
        $properties = [];

        foreach (array_keys($component->effectiveProperties()) as $key) {
            $key = (string) $key;

            if ($key === 'default' || $definition->property($key)?->isCallback() === true) {
                continue;
            }

            $properties[$key] = $component->evaluateProperty($key, $context);
        }

        return $properties;
    }

    /**
     * The component's own state path (for state holders) and the container
     * path its children use.
     *
     * @return array{0: ?string, 1: string}
     */
    private function paths(ComponentInterface $component, string $containerPath): array
    {
        if (! $component instanceof Component) {
            return [null, $containerPath];
        }

        $segment = $component->evaluateProperty('statePath', $this->context);
        $segment = is_string($segment) && $segment !== '' ? $segment : null;

        if ($component instanceof HoldsState) {
            $own = $segment ?? $component->name();

            return [$own === null ? null : self::join($containerPath, $own), $containerPath];
        }

        return [null, $segment === null ? $containerPath : self::join($containerPath, $segment)];
    }

    private function contextFor(string $containerPath): EvaluationContext
    {
        return $this->context->withState($this->state, $containerPath);
    }

    /**
     * @return array<string, string>
     */
    private function fingerprints(): array
    {
        $fingerprints = [];

        foreach ($this->index as $path => $component) {
            $fingerprints[(string) $path] = md5(serialize([
                $component->hidden,
                $component->label,
                Components::normalizeValue($component->properties, $component->path),
                $component->statePath === null ? null : $this->state->peek($component->statePath),
                count($component->children),
            ]));
        }

        return $fingerprints;
    }

    /**
     * @return array<string, ResolvedComponent>
     */
    private static function preOrder(ResolvedComponent $component): array
    {
        $index = [$component->path => $component];

        foreach ($component->children as $child) {
            $index += self::preOrder($child);
        }

        return $index;
    }

    private static function join(string $containerPath, string $segment): string
    {
        return $containerPath === '' ? $segment : $containerPath.'.'.$segment;
    }
}
