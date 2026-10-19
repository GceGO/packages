<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\ModuleManager;
use Gcego\Modules\Operation;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Container\Container;

/**
 * A gcego/modules module as compiled: its resources, the operations and
 * pages generated for them, relations, routes and inspector findings.
 * Opening the generated pages is PageTestable's job.
 */
final class ModuleTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly string $module)
    {
        parent::__construct($verifier);
    }

    public function definition(): ModuleDefinition
    {
        return $this->manager()->module($this->module);
    }

    public function assertRegistered(): self
    {
        return $this->check($this->manager()->has($this->module), 'assertRegistered()', sprintf('Expected the module [%s] to be registered.', $this->module), ['registered' => true], ['modules' => $this->manager()->names()]);
    }

    public function assertHasResource(string $resource): self
    {
        return $this->check($this->resource($resource) !== null, sprintf('assertHasResource(%s)', $this->export($resource)), sprintf('Expected the resource [%s].', $resource), ['resource' => $resource], ['resources' => array_values(array_map(static fn (ResourceDefinition $definition): string => $definition->name, $this->definition()->resources))]);
    }

    /**
     * Exactly these operations get pages (index, create, view, edit).
     *
     * @param  list<Operation|string>  $operations
     */
    public function assertOperations(string $resource, array $operations): self
    {
        $expected = array_map(static fn (Operation|string $operation): string => $operation instanceof Operation ? $operation->value : $operation, $operations);
        $actual = array_values(array_map(static fn (Operation $operation): string => $operation->value, array_filter(Operation::cases(), fn (Operation $operation): bool => $this->resource($resource)?->exposes($operation) ?? false)));
        sort($expected);
        sort($actual);

        return $this->check($expected === $actual, sprintf('assertOperations(%s, %s)', $this->export($resource), $this->export($expected)), sprintf('Expected the resource [%s] to expose exactly these operations.', $resource), ['operations' => $expected], ['operations' => $actual], 'Index needs a Table, View an Infolist, Create and Edit a Form plus the action.');
    }

    public function assertPage(string $resource, Operation $operation, ?string $identity = null, ?string $relation = null): self
    {
        $actual = $this->manager()->page($this->module, $resource, $operation, $relation);

        return $this->check($actual !== null && ($identity === null || $actual === $identity), sprintf('assertPage(%s, %s, %s)', $this->export($resource), $operation->value, $this->export($identity)), sprintf('Expected a %s page for [%s].', $operation->value, $resource), ['page' => $identity ?? '(any)'], ['page' => $actual]);
    }

    public function assertRouteName(string $resource, Operation $operation, string $route): self
    {
        $actual = $this->resource($resource)?->routeName($operation);

        return $this->check($actual === $route, sprintf('assertRouteName(%s, %s, %s)', $this->export($resource), $operation->value, $this->export($route)), 'Expected the route name (without the pages prefix).', ['route' => $route], ['route' => $actual]);
    }

    /**
     * @param  array<string, string|int>  $parameters
     */
    public function assertUrl(string $resource, Operation $operation, string $url, string|int|null $record = null, array $parameters = []): self
    {
        $actual = $this->manager()->url($this->module, $resource, $operation, $record, $parameters);

        return $this->check($actual === $url, sprintf('assertUrl(%s, %s, %s)', $this->export($resource), $operation->value, $this->export($url)), 'Expected the page URL.', ['url' => $url], ['url' => $actual]);
    }

    public function assertHasRelation(string $resource, string $relation): self
    {
        $actual = $this->resource($resource)?->relation($relation);

        return $this->check($actual !== null, sprintf('assertHasRelation(%s, %s)', $this->export($resource), $this->export($relation)), sprintf('Expected the relation manager [%s] on [%s].', $relation, $resource), ['relation' => $relation], ['relations' => array_values(array_map(static fn (object $definition): string => property_exists($definition, 'name') && is_string($definition->name) ? $definition->name : $definition::class, $this->resource($resource)->relations ?? []))]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->module));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the module inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'module';
    }

    protected function context(): array
    {
        return ['Module' => $this->module];
    }

    private function resource(string $resource): ?ResourceDefinition
    {
        return $this->definition()->resource($resource);
    }

    private function manager(): ModuleManager
    {
        return $this->container->make(ModuleManager::class);
    }
}
