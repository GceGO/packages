<?php

declare(strict_types=1);

namespace Gcego\Components\Normalization;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Contracts\RuntimeValue;
use Gcego\Components\Contracts\ValueObject;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Support\ValueDescriber;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use UnitEnum;

/**
 * Converts a component tree into a canonical, transport-independent array.
 *
 * Output per component:
 *
 *     [
 *         'type' => string,             // schema type identifier
 *         'class' => string,            // class label without anonymous source locations
 *         'id' => ?string,              // explicit id, never generated
 *         'path' => string,             // deterministic occurrence path
 *         'properties' => array,        // defaults merged with explicit values, sorted by key
 *         'configurations' => list<array{class: string, data: mixed}>,
 *         'children' => list<array>,    // same shape, in declaration order
 *         'schema' => array,            // only when $includeSchema is true
 *     ]
 *
 * Values keep their PHP form where it is safe and immutable: scalars, null,
 * arrays and enum cases. ValueObjects are replaced by their normalize() data
 * and RuntimeValues by a RuntimePlaceholder. The output is not JSON; a
 * transport layer decides how to encode enums and placeholders.
 */
final readonly class ComponentNormalizer
{
    private const int MaxValueDepth = 64;

    public function __construct(
        private ComponentWalker $walker,
        private bool $includeSchema = false,
    ) {
        Components::getFacadeRoot();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(ComponentInterface $root): array
    {
        /** @var array<string, list<array<string, mixed>>> $pendingChildren */
        $pendingChildren = [];

        foreach ($this->walker->postOrder($root) as $node) {
            $path = $node->path->toString();
            $normalized = $this->normalizeNode($node, $pendingChildren[$path] ?? []);
            unset($pendingChildren[$path]);

            if ($node->parentPath === null) {
                return $normalized;
            }

            $pendingChildren[$node->parentPath->toString()][] = $normalized;
        }

        return [];
    }

    public function normalizeValue(mixed $value, string $location = 'value'): mixed
    {
        return $this->normalizeValueAt($value, $location, 0);
    }

    /**
     * Apply schema normalizers and normalize supported values in a property map.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function normalizeProperties(array $values, ComponentDefinition $definition, string $location = 'component'): array
    {
        $properties = [];

        foreach ($values as $key => $value) {
            $property = $definition->property($key);
            // Invalid values remain inspectable without invoking a typed callback
            // with input outside the schema's contract.
            $normalizer = ! $value instanceof RuntimeValue && $property !== null && $property->acceptsType($value) ? $property->normalizer : null;
            $properties[$key] = $this->normalizeValue(
                $normalizer === null ? $value : $normalizer($value),
                sprintf('%s property "%s"', $location, $key),
            );
        }

        ksort($properties);

        return $properties;
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function normalizeNode(TreeNode $node, array $children): array
    {
        $component = $node->component;
        $definition = $component::definition();
        $path = $node->path->toString();

        $configurations = [];

        foreach ($component->configurations() as $index => $configuration) {
            $configurations[] = $this->normalizeConfiguration($configuration, sprintf('%s configuration #%d', $path, $index));
        }

        $normalized = [
            'type' => $component->type(),
            'class' => ValueDescriber::className($component::class),
            'id' => $component->id(),
            'path' => $path,
            'properties' => $this->normalizeProperties($component->effectiveProperties(), $definition, $path),
            'configurations' => $configurations,
            'children' => $children,
        ];

        if ($this->includeSchema) {
            $normalized['schema'] = $this->normalizeValue($definition->toArray(), $path.' schema');
        }

        return $normalized;
    }

    /**
     * @return array{class: string, data: mixed}
     */
    private function normalizeConfiguration(ConfigurationNode $configuration, string $location): array
    {
        return [
            'class' => ValueDescriber::className($configuration::class),
            'data' => $this->normalizeValue($configuration->normalize(), $location),
        ];
    }

    private function normalizeValueAt(mixed $value, string $location, int $depth): mixed
    {
        if ($depth > self::MaxValueDepth) {
            throw InvalidConfigurationException::notNormalizable($location, $value);
        }

        if ($value === null || is_scalar($value) || $value instanceof UnitEnum) {
            return $value;
        }

        if ($value instanceof RuntimePlaceholder) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValueAt($item, $location.'['.$key.']', $depth + 1);
            }

            return $normalized;
        }

        if ($value instanceof RuntimeValue) {
            return new RuntimePlaceholder($value::class);
        }

        if ($value instanceof ValueObject) {
            return $this->normalizeValueAt($value->normalize(), $location, $depth + 1);
        }

        throw InvalidConfigurationException::notNormalizable($location, $value);
    }
}
