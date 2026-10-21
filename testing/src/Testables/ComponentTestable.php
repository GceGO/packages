<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use BackedEnum;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Facades\Components;
use Gcego\Components\Inspection\ComponentInspection;
use Gcego\Components\Trees\ComponentTree;
use Gcego\Components\Trees\TreeNode;
use Gcego\Components\Validation\ValidationError;
use Gcego\Testing\Assertions\Verifier;

/**
 * Assertions about one gcego/morfaw definition and its subtree.
 *
 * Nothing here mutates the component: definitions are immutable and every
 * check reads validate(), normalize() or a ComponentTree.
 */
final class ComponentTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly ComponentInterface $component)
    {
        parent::__construct($verifier);
    }

    public function component(): ComponentInterface
    {
        return $this->component;
    }

    public function inspection(): ComponentInspection
    {
        return Components::inspect($this->component);
    }

    public function assertValid(): self
    {
        $errors = $this->component->validate()->errors();

        return $this->check($errors === [], 'assertValid()', sprintf('Expected the component [%s] to be valid.', $this->component::class), ['errors' => 0], ['errors' => count($errors), 'all' => array_map(static fn (ValidationError $error): string => $error->format(), $errors)], 'Fix the listed errors; each one names the path and the property.');
    }

    /**
     * Passes when validation reports at least one error; narrow it with an
     * error code (e.g. ErrorCode::MissingProperty or "missing_property")
     * and/or the component path the error is reported at.
     */
    public function assertInvalid(BackedEnum|string|null $code = null, ?string $path = null): self
    {
        $code = $code instanceof BackedEnum ? (string) $code->value : $code;
        $errors = $this->component->validate()->errors();
        $matching = array_filter($errors, static fn (ValidationError $error): bool => ($code === null || $error->code->value === $code) && ($path === null || $error->path === $path));

        return $this->check($matching !== [], sprintf('assertInvalid(%s, %s)', $this->export($code), $this->export($path)), 'Expected the component to report a validation error'.($code !== null ? " [$code]" : '').($path !== null ? " at [$path]" : '').'.', ['code' => $code, 'path' => $path], ['errors' => array_map(static fn (ValidationError $error): string => $error->code->value.' at '.$error->path, $errors)]);
    }

    public function assertType(string $type): self
    {
        $actual = $this->component->type();

        return $this->check($actual === $type || $this->component instanceof $type, sprintf('assertType(%s)', $this->export($type)), sprintf('Expected the component type [%s].', $type), ['type' => $type], ['type' => $actual, 'class' => $this->component::class]);
    }

    public function assertComponentType(string $type): self
    {
        return $this->assertType($type);
    }

    public function assertName(?string $name): self
    {
        $actual = $this->component->get(Component::Name);

        return $this->check($actual === $name, sprintf('assertName(%s)', $this->export($name)), sprintf('Expected the component name [%s].', $name), ['name' => $name], ['name' => $actual]);
    }

    public function assertLabel(?string $label): self
    {
        $actual = $this->labelOf($this->component);

        return $this->check($actual === $label, sprintf('assertLabel(%s)', $this->export($label)), sprintf('Expected the component label [%s].', $label), ['label' => $label], ['label' => $actual], 'Components with a label() method (fields, columns, entries) are read through it; others through the "label" property.');
    }

    public function assertId(?string $id): self
    {
        $actual = $this->component->id();

        return $this->check($actual === $id, sprintf('assertId(%s)', $this->export($id)), sprintf('Expected the component id [%s].', $id), ['id' => $id], ['id' => $actual]);
    }

    /**
     * True when the property has an explicit value or a default.
     */
    public function assertHasProperty(string $key): self
    {
        return $this->check($this->component->has($key), sprintf('assertHasProperty(%s)', $this->export($key)), sprintf('Expected the component to have the property [%s].', $key), ['property' => $key], ['properties' => array_keys($this->component->effectiveProperties())]);
    }

    public function assertMissingProperty(string $key): self
    {
        return $this->check(! $this->component->has($key), sprintf('assertMissingProperty(%s)', $this->export($key)), sprintf('Expected the component not to have the property [%s].', $key), ['property' => 'absent'], ['property' => $this->component->get($key)]);
    }

    public function assertPropertyEquals(string $key, mixed $value): self
    {
        $actual = $this->component->get($key);

        return $this->check($actual === $value, sprintf('assertPropertyEquals(%s, %s)', $this->export($key), $this->export($value)), sprintf('Expected the property [%s] to equal the given value.', $key), [$key => $value], [$key => $actual]);
    }

    /**
     * The definition default of a property (not the explicit value).
     */
    public function assertDefault(string $key, mixed $value): self
    {
        $defaults = $this->component::definition()->defaults();

        return $this->check(array_key_exists($key, $defaults) && $defaults[$key] === $value, sprintf('assertDefault(%s, %s)', $this->export($key), $this->export($value)), sprintf('Expected the property [%s] to default to the given value.', $key), [$key => $value], [$key => array_key_exists($key, $defaults) ? $defaults[$key] : '(no default)']);
    }

    public function assertRequiresProperty(string $key): self
    {
        $required = array_keys($this->component::definition()->requiredProperties());

        return $this->check(in_array($key, $required, true), sprintf('assertRequiresProperty(%s)', $this->export($key)), sprintf('Expected the definition of [%s] to require [%s].', $this->component::class, $key), ['required' => $key], ['required' => $required]);
    }

    /**
     * Whether the definition accepts the value for the property: a value of
     * the wrong type, outside the allowed values or failing the rule is
     * rejected. The component itself is not changed.
     */
    public function assertAcceptsValue(string $key, mixed $value): self
    {
        $error = $this->propertyError($key, $value);

        return $this->check($error === null, sprintf('assertAcceptsValue(%s, %s)', $this->export($key), $this->export($value)), sprintf('Expected the property [%s] to accept the value.', $key), ['accepted' => true], ['error' => $error]);
    }

    public function assertRejectsValue(string $key, mixed $value): self
    {
        $error = $this->propertyError($key, $value);

        return $this->check($error !== null, sprintf('assertRejectsValue(%s, %s)', $this->export($key), $this->export($value)), sprintf('Expected the property [%s] to reject the value.', $key), ['accepted' => false], ['accepted' => true]);
    }

    /**
     * A child named $name (its "name" property), or a node at a path
     * relative to this component, e.g. "stats.students".
     */
    public function assertHasChild(string $nameOrPath): self
    {
        return $this->check($this->node($nameOrPath) !== null, sprintf('assertHasChild(%s)', $this->export($nameOrPath)), sprintf('Expected the component to contain [%s].', $nameOrPath), ['child' => $nameOrPath], ['paths' => $this->paths()]);
    }

    public function assertMissingChild(string $nameOrPath): self
    {
        return $this->check($this->node($nameOrPath) === null, sprintf('assertMissingChild(%s)', $this->export($nameOrPath)), sprintf('Expected the component not to contain [%s].', $nameOrPath), ['child' => 'absent'], ['paths' => $this->paths()]);
    }

    public function assertChildCount(int $count): self
    {
        $actual = $this->component->childCount();

        return $this->check($actual === $count, sprintf('assertChildCount(%d)', $count), sprintf('Expected %d direct children.', $count), ['children' => $count], ['children' => $actual]);
    }

    /**
     * Somewhere in the subtree, a component of this type or class.
     */
    public function assertContainsType(string $type, ?int $times = null): self
    {
        $found = count(Components::tree($this->component)->findByType($type));

        return $this->check($times === null ? $found > 0 : $found === $times, sprintf('assertContainsType(%s, %s)', $this->export($type), $this->export($times)), sprintf('Expected the subtree to contain [%s]%s.', $type, $times === null ? '' : " $times time(s)"), ['count' => $times ?? '≥ 1'], ['count' => $found]);
    }

    public function assertNoDuplicateIds(): self
    {
        $duplicates = Components::tree($this->component)->duplicateIds();

        return $this->check($duplicates === [], 'assertNoDuplicateIds()', 'Expected every explicit id in the subtree to be unique.', ['duplicates' => []], ['duplicates' => $duplicates]);
    }

    /**
     * normalize() is deterministic and, when given, contains $subset
     * (compared recursively, keys and values).
     *
     * @param  array<string, mixed>  $subset
     */
    public function assertNormalized(array $subset = []): self
    {
        $first = $this->component->normalize();
        $second = $this->component->normalize();
        $missing = $this->missingFrom($subset, $first);

        return $this->check($first === $second && $missing === [], 'assertNormalized('.$this->export($subset).')', $first !== $second ? 'Expected normalize() to return the same array every time.' : 'Expected the normalized component to contain the given values.', ['subset' => $subset], ['differences' => $missing]);
    }

    /**
     * A ComponentTestable for the node at a relative path or child name.
     */
    public function child(string $nameOrPath): self
    {
        $node = $this->node($nameOrPath);
        $this->assertHasChild($nameOrPath);

        return new self($this->verifier, $node === null ? $this->component : $node->component);
    }

    protected function subject(): string
    {
        return 'component';
    }

    protected function context(): array
    {
        return ['Component' => $this->component::class, 'Type' => $this->component->type(), 'Name' => $this->component->get(Component::Name)];
    }

    private function node(string $nameOrPath): ?TreeNode
    {
        foreach ($this->component->children()->all() as $child) {
            if ($child->get(Component::Name) === $nameOrPath) {
                return Components::tree($child)->rootNode();
            }
        }

        $tree = Components::tree($this->component);
        $root = (string) $tree->rootNode()->path;

        return $tree->find($root.'.'.$nameOrPath) ?? $tree->find($nameOrPath);
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return array_map(static fn (TreeNode $node): string => (string) $node->path, Components::tree($this->component)->depthFirst());
    }

    private function labelOf(ComponentInterface $component): mixed
    {
        if (method_exists($component, 'label')) {
            return $component->label();
        }

        return $component->get(Component::Label);
    }

    private function propertyError(string $key, mixed $value): ?string
    {
        $definition = $this->component::definition()->property($key);

        if ($definition === null) {
            return $this->component::definition()->allowsUnknownProperties ? null : 'unknown property';
        }

        return match (true) {
            $value === null && ! $definition->nullable => 'not nullable',
            $value !== null && ! $definition->acceptsType($value) => 'expected '.$definition->expectedType(),
            $value !== null && ! $definition->allowsValue($value) => 'not an allowed value',
            $value !== null && ! $definition->satisfiesRule($value) => 'fails the rule',
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $subset
     * @param  array<array-key, mixed>  $actual
     * @return array<string, mixed>
     */
    private function missingFrom(array $subset, array $actual, string $prefix = ''): array
    {
        $missing = [];

        foreach ($subset as $key => $value) {
            $path = $prefix.$key;

            if (! array_key_exists($key, $actual)) {
                $missing[$path] = '(missing)';
            } elseif (is_array($value) && is_array($actual[$key])) {
                $missing = [...$missing, ...$this->missingFrom($value, $actual[$key], $path.'.')];
            } elseif ($actual[$key] !== $value) {
                $missing[$path] = $actual[$key];
            }
        }

        return $missing;
    }
}
