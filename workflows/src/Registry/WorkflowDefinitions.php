<?php

declare(strict_types=1);

namespace Gcego\Workflows\Registry;

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Workflow;
use Throwable;

/**
 * Registered workflows by name (validated before any lookup), and the
 * definition a running instance may continue with:
 *
 * - the same version: always;
 * - another version: only when the current definition lists the
 *   instance's version in Compatible and still has the instance's state;
 * - otherwise: refused (WorkflowDefinitionException), never reinterpreted.
 */
final readonly class WorkflowDefinitions
{
    public function __construct(private RegistryInterface $registry) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_map(static fn ($descriptor): string => $descriptor->name(), $this->registry->descriptors(WorkflowKind::Id));
        sort($names);

        return $names;
    }

    public function has(string $name): bool
    {
        return Workflow::validName($name) && $this->registry->has(WorkflowKind::Id.':'.$name);
    }

    /**
     * @throws WorkflowNotFoundException
     */
    public function resolve(string $name): Workflow
    {
        if (! $this->has($name)) {
            throw WorkflowNotFoundException::make('workflow');
        }

        try {
            $workflow = $this->registry->get(WorkflowKind::Id.':'.$name);
        } catch (Throwable) {
            throw WorkflowNotFoundException::make('workflow');
        }

        return $workflow instanceof Workflow ? $workflow : throw WorkflowNotFoundException::make('workflow');
    }

    /**
     * @throws WorkflowDefinitionException when the instance's version cannot continue
     */
    public function forInstance(WorkflowInstance $instance): Workflow
    {
        $workflow = $this->resolve($instance->workflow);

        if ($workflow->version() === $instance->version) {
            return $workflow;
        }

        if (in_array($instance->version, $workflow->compatibleVersions(), true) && $workflow->state($instance->state) !== null) {
            return $workflow;
        }

        throw WorkflowDefinitionException::invalid(sprintf('Workflow "%s" version %d', $instance->workflow, $workflow->version()), sprintf('it cannot continue an instance of version %d; list %d in Compatible (when its states still fit) or migrate the instance.', $instance->version, $instance->version));
    }
}
