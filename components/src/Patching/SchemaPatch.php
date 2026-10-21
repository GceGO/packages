<?php

declare(strict_types=1);

namespace Gcego\Components\Patching;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\PatchException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;

/**
 * Immutable edits to a built schema, so one module can extend another's
 * schema without copying it:
 *
 *     Components::patch($schema)
 *         ->insertAfter('account.email', TextInput::make('phone'))
 *         ->modify('email', fn (ComponentBuilder $email) => $email->required())
 *         ->remove('legacy_code')
 *         ->apply();
 *
 * A selector is a dot-separated chain of component names matched against
 * the end of each component's tree path: "email" matches every component
 * named email, "account.email" only the one inside "account". Use "name#1"
 * for a repeated sibling. Every selector must match, or the patch fails
 * with a suggestion.
 */
final class SchemaPatch
{
    /**
     * @var list<array{operation: string, selector: string, payload: mixed}>
     */
    private array $operations = [];

    public function __construct(private readonly ComponentInterface $schema)
    {
        Components::getFacadeRoot();
    }

    /**
     * Replace matching components with what the callback returns. The
     * callback receives a builder holding the component's properties,
     * children and configuration nodes.
     *
     * @param  Closure(ComponentBuilder): (ComponentBuilder|ComponentInterface)  $callback
     */
    public function modify(string $selector, Closure $callback): self
    {
        return $this->with('modify', $selector, $callback);
    }

    public function remove(string $selector): self
    {
        return $this->with('remove', $selector, null);
    }

    public function insertBefore(string $selector, ComponentInterface|Buildable ...$components): self
    {
        return $this->with('insertBefore', $selector, $components);
    }

    public function insertAfter(string $selector, ComponentInterface|Buildable ...$components): self
    {
        return $this->with('insertAfter', $selector, $components);
    }

    /**
     * Add children at the end of matching containers.
     */
    public function append(string $selector, ComponentInterface|Buildable ...$components): self
    {
        return $this->with('append', $selector, $components);
    }

    /**
     * Add children at the start of matching containers.
     */
    public function prepend(string $selector, ComponentInterface|Buildable ...$components): self
    {
        return $this->with('prepend', $selector, $components);
    }

    /**
     * Build the patched schema. The original is never changed.
     */
    public function apply(): ComponentInterface
    {
        $walker = Components::getFacadeRoot()->walker();
        $root = $walker->rootNode($this->schema);
        $paths = [];

        foreach ($walker->preOrder($this->schema) as $node) {
            $paths[] = $node->path->segments();
        }

        foreach ($this->operations as $operation) {
            $matched = array_filter(array_slice($paths, 1), static fn (array $segments): bool => self::matches($segments, $operation['selector']));

            if ($matched === []) {
                throw PatchException::noMatch($operation['selector'], self::suggest($operation['selector'], array_slice($paths, 1)));
            }
        }

        $patched = $this->rebuild($walker, $root, true);

        return $patched[0];
    }

    /**
     * @return list<ComponentInterface>
     */
    private function rebuild(ComponentWalker $walker, TreeNode $node, bool $isRoot = false): array
    {
        $component = $node->component;
        $original = $component->children()->all();
        $children = [];

        foreach ($walker->childNodes($node) as $child) {
            array_push($children, ...$this->rebuild($walker, $child));
        }

        if ($children !== $original) {
            $component = $this->copyable($component, $node, 'patch')->withChildren(...$children);
        }

        $before = [];
        $after = [];
        $removed = false;
        $segments = $node->path->segments();

        foreach ($this->operations as $operation) {
            if ($isRoot || ! self::matches($segments, $operation['selector'])) {
                continue;
            }

            $payload = $operation['payload'];
            $selector = $operation['selector'];

            match ($operation['operation']) {
                'modify' => $component = $this->modified($component, $node, $selector, $payload),
                'remove' => $removed = true,
                'insertBefore' => array_push($before, ...self::built($payload)),
                'insertAfter' => array_push($after, ...self::built($payload)),
                'append' => $component = $this->copyable($component, $node, 'append')->withAppendedChildren(...self::built($payload)),
                'prepend' => $component = $this->copyable($component, $node, 'prepend')->withChildren(...self::built($payload), ...$component->children()->all()),
                default => null,
            };
        }

        return [...$before, ...($removed ? [] : [$component]), ...$after];
    }

    private function modified(ComponentInterface $component, TreeNode $node, string $selector, mixed $callback): ComponentInterface
    {
        $component = $this->copyable($component, $node, 'modify');

        if (! $callback instanceof Closure) {
            throw PatchException::invalidModification($selector);
        }

        $builder = Components::define($component::class, [
            ...array_values($component->properties()->all()),
            ...$component->children()->all(),
            ...$component->configurations()->all(),
        ]);

        $result = $callback($builder);

        if ($result instanceof Buildable) {
            $result = $result->build();
        }

        if (! $result instanceof ComponentInterface) {
            throw PatchException::invalidModification($selector);
        }

        return $result;
    }

    private function copyable(ComponentInterface $component, TreeNode $node, string $operation): Component
    {
        if (! $component instanceof Component) {
            throw PatchException::notAComponent($node->path->toString(), $operation);
        }

        return $component;
    }

    /**
     * @return list<ComponentInterface>
     */
    private static function built(mixed $components): array
    {
        $built = [];

        foreach (is_array($components) ? $components : [] as $component) {
            if ($component instanceof Buildable) {
                $built[] = $component->build();
            } elseif ($component instanceof ComponentInterface) {
                $built[] = $component;
            }
        }

        return $built;
    }

    private function with(string $operation, string $selector, mixed $payload): self
    {
        $copy = clone $this;
        $copy->operations[] = ['operation' => $operation, 'selector' => trim($selector, '.'), 'payload' => $payload];

        return $copy;
    }

    /**
     * @param  list<string>  $segments  The node's tree path segments, root first.
     */
    private static function matches(array $segments, string $selector): bool
    {
        $wanted = explode('.', $selector);
        $tail = array_slice(array_slice($segments, 1), -count($wanted));

        if (count($tail) !== count($wanted)) {
            return false;
        }

        foreach ($wanted as $index => $segment) {
            $actual = $tail[$index];

            if ($actual !== $segment && (str_contains($segment, '#') || preg_match('/\A'.preg_quote($segment, '/').'#\d+\z/', $actual) !== 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<list<string>>  $paths
     */
    private static function suggest(string $selector, array $paths): ?string
    {
        $count = count(explode('.', $selector));
        $best = null;
        $bestDistance = 4;

        foreach ($paths as $segments) {
            $candidate = implode('.', array_slice(array_slice($segments, 1), -$count));
            $distance = levenshtein(strtolower($selector), strtolower($candidate));

            if ($candidate !== '' && $distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
