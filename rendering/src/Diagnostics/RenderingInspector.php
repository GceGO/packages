<?php

declare(strict_types=1);

namespace Gcego\Rendering\Diagnostics;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Rendering\Renderers\RendererBinding;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Rendering\RenderingConfig;
use Illuminate\Contracts\View\Factory;
use Throwable;

/**
 * Explains rendering: renderers, overrides, views and, for a given
 * definition, which components lack a renderer. Never throws.
 */
final readonly class RenderingInspector
{
    public const array Views = [
        'gcego-rendering::renderers.container',
        'gcego-rendering::errors.component',
        'gcego-rendering::components.render',
    ];

    public function __construct(
        private RendererRegistry $renderers,
        private RenderingConfig $config,
        private Factory $views,
        private RegistryInterface $registry,
    ) {}

    public function inspect(ComponentInterface|string|null $target = null): RenderingReport
    {
        $diagnostics = [];

        $diagnostics[] = new RenderDiagnostic('config', RenderDiagnostic::Ok, 'Rendering configuration loaded.', [
            'strict' => $this->config->strict,
            'validate' => $this->config->validate,
            'maxDepth' => $this->config->maxDepth,
            'ability' => $this->config->ability,
        ]);

        foreach ($this->renderers->overrides() as $override) {
            $diagnostics[] = new RenderDiagnostic('renderers.override', RenderDiagnostic::Warning, sprintf('%s replaced %s for %s.', $override['current'], $override['previous'], $override['target']), $override);
        }

        foreach (self::Views as $view) {
            $exists = $this->views->exists($view);
            $diagnostics[] = new RenderDiagnostic('views', $exists ? RenderDiagnostic::Ok : RenderDiagnostic::Error, sprintf('View [%s] %s.', $view, $exists ? 'is available' : 'is missing'), ['view' => $view]);
        }

        $missing = [];

        if ($target !== null) {
            [$diagnostics, $missing] = $this->inspectTarget($target, $diagnostics);
        }

        return new RenderingReport(
            $diagnostics,
            array_map(static fn (RendererBinding $binding): array => $binding->toArray(), $this->renderers->bindings()),
            $missing,
        );
    }

    /**
     * @param  list<RenderDiagnostic>  $diagnostics
     * @return array{0: list<RenderDiagnostic>, 1: list<array{path: string, type: string, class: class-string}>}
     */
    private function inspectTarget(ComponentInterface|string $target, array $diagnostics): array
    {
        $missing = [];

        try {
            $component = is_string($target) ? $this->registry->get($target) : $target;

            if (! $component instanceof ComponentInterface) {
                $diagnostics[] = new RenderDiagnostic('target', RenderDiagnostic::Error, sprintf('%s is not a component.', $component::class));

                return [$diagnostics, $missing];
            }

            $count = 0;

            foreach ((new ComponentWalker($this->config->maxDepth))->preOrder($component) as $node) {
                $count++;

                if (! $this->renderers->has($node->component)) {
                    $missing[] = ['path' => $node->path->toString(), 'type' => $node->component->type(), 'class' => $node->component::class];
                }
            }

            $validation = $component->validate();

            $diagnostics[] = new RenderDiagnostic(
                'target.renderers',
                $missing === [] ? RenderDiagnostic::Ok : RenderDiagnostic::Error,
                $missing === [] ? sprintf('All %d component(s) have renderers.', $count) : sprintf('%d of %d component(s) have no renderer.', count($missing), $count),
                ['components' => $count, 'missing' => count($missing)],
            );
            $diagnostics[] = new RenderDiagnostic(
                'target.validation',
                $validation->isValid() ? RenderDiagnostic::Ok : RenderDiagnostic::Error,
                $validation->isValid() ? 'The definition is valid.' : sprintf('The definition has %d validation error(s).', $validation->count()),
            );
        } catch (Throwable $exception) {
            $diagnostics[] = new RenderDiagnostic('target', RenderDiagnostic::Error, $exception->getMessage(), ['exception' => $exception::class]);
        }

        return [$diagnostics, $missing];
    }
}
