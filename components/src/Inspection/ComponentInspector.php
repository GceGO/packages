<?php

declare(strict_types=1);

namespace Gcego\Components\Inspection;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\TreeNode;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Components\Validation\ValidationError;

/**
 * Produces structured inspection snapshots for CLIs, debuggers and IDE tooling.
 *
 * Property values are passed through the normalizer so inspections never
 * expose closures or runtime objects.
 */
final readonly class ComponentInspector
{
    public function __construct(
        private ComponentValidator $validator = new ComponentValidator,
        private ComponentNormalizer $normalizer = new ComponentNormalizer,
        private ComponentWalker $walker = new ComponentWalker,
    ) {}

    /**
     * Inspect one component; errors cover the component and all of its descendants.
     */
    public function inspect(ComponentInterface $component): ComponentInspection
    {
        return $this->inspectNode($this->walker->rootNode($component), $this->validator->validate($component)->errors());
    }

    /**
     * Inspect every occurrence in document order; each entry lists only its own errors.
     *
     * @return list<ComponentInspection>
     */
    public function inspectTree(ComponentInterface $root): array
    {
        $errorsByPath = [];

        foreach ($this->validator->validate($root) as $error) {
            $errorsByPath[$error->path][] = $error;
        }

        $inspections = [];

        foreach ($this->walker->preOrder($root) as $node) {
            $inspections[] = $this->inspectNode($node, $errorsByPath[$node->path->toString()] ?? []);
        }

        return $inspections;
    }

    /**
     * @param  list<ValidationError>  $errors
     */
    private function inspectNode(TreeNode $node, array $errors): ComponentInspection
    {
        $component = $node->component;
        $definition = $component::definition();

        return new ComponentInspection(
            class: $component::class,
            type: $component->type(),
            id: $component->id(),
            path: $node->path->toString(),
            depth: $node->depth,
            supportedProperties: $definition->propertyNames(),
            requiredProperties: $definition->requiredProperties(),
            allowsUnknownProperties: $definition->allowsUnknownProperties,
            explicitProperties: $this->normalizeMap($component->properties()->toArray()),
            effectiveProperties: $this->normalizeMap($component->effectiveProperties()),
            defaults: $this->normalizeMap($definition->defaults()),
            childCount: $component->childCount(),
            childrenAllowed: $definition->allowsChildren,
            allowedChildren: $definition->allowedChildren,
            minChildren: $definition->minChildren,
            maxChildren: $definition->maxChildren,
            configurationClasses: array_map(
                static fn (ConfigurationNode $configuration): string => $configuration::class,
                $component->configurations()->all(),
            ),
            errors: $errors,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeMap(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$key] = $this->normalizer->normalizeValue($value, sprintf('property "%s"', $key));
        }

        return $normalized;
    }
}
