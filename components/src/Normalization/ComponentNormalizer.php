<?php

declare(strict_types=1);

namespace Gcego\Components\Normalization;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Contracts\RuntimeValue;
use Gcego\Components\Contracts\ValueObject;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\TreeNode;
use UnitEnum;

/**
 * Converts a component tree into a canonical, transport-independent array.
 *
 * Output per component:
 *
 *     [
 *         'type' => string,             // schema type identifier
 *         'class' => class-string,      // concrete PHP class
 *         'id' => ?string,              // explicit id, never generated
 *         'path' => string,             // deterministic occurrence path
 *         'properties' => array,        // defaults merged with explicit values, sorted by key
 *         'configurations' => list<array{class: class-string, data: mixed}>,
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
        private bool $includeSchema = false,
        private ComponentWalker $walker = new ComponentWalker,
    ) {}

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
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function normalizeNode(TreeNode $node, array $children): array
    {
        $component = $node->component;
        $definition = $component::definition();
        $path = $node->path->toString();
        $properties = [];

        foreach ($component->effectiveProperties() as $key => $value) {
            $normalizer = $definition->property($key)?->normalizer;
            $properties[$key] = $this->normalizeValue(
                $normalizer === null ? $value : $normalizer($value),
                sprintf('%s property "%s"', $path, $key),
            );
        }

        $configurations = [];

        foreach ($component->configurations() as $index => $configuration) {
            $configurations[] = $this->normalizeConfiguration($configuration, sprintf('%s configuration #%d', $path, $index));
        }

        $normalized = [
            'type' => $component->type(),
            'class' => $component::class,
            'id' => $component->id(),
            'path' => $path,
            'properties' => $properties,
            'configurations' => $configurations,
            'children' => $children,
        ];

        if ($this->includeSchema) {
            $normalized['schema'] = $definition->toArray();
        }

        return $normalized;
    }

    /**
     * @return array{class: class-string, data: mixed}
     */
    private function normalizeConfiguration(ConfigurationNode $configuration, string $location): array
    {
        return [
            'class' => $configuration::class,
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

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValueAt($item, $location.'['.$key.']', $depth + 1);
            }

            return $normalized;
        }

        if ($value instanceof ValueObject) {
            return $this->normalizeValueAt($value->normalize(), $location, $depth + 1);
        }

        if ($value instanceof RuntimeValue) {
            return new RuntimePlaceholder($value::class);
        }

        throw InvalidConfigurationException::notNormalizable($location, $value);
    }
}
