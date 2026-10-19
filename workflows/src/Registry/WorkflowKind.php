<?php

declare(strict_types=1);

namespace Gcego\Workflows\Registry;

use Gcego\Components\Validation\ValidationError;
use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Workflows\Workflow;
use InvalidArgumentException;

/**
 * The "workflow" kind of gcego/registry ("workflow:institution-registration"),
 * discovered from app/GCEGo/Workflows (every PHP file returns a Workflow).
 */
final readonly class WorkflowKind implements DefinitionKind
{
    public const string Id = 'workflow';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Workflow::class;
    }

    public function name(object $definition): string
    {
        $name = $this->workflow($definition)->workflowName();

        if (! Workflow::validName($name)) {
            throw new InvalidArgumentException('A workflow needs a Name such as "institution-registration".');
        }

        return $name;
    }

    public function validate(object $definition): array
    {
        return array_map(static fn (ValidationError $error): string => $error->format(), $this->workflow($definition)->validate()->errors());
    }

    public function metadata(object $definition): array
    {
        $workflow = $this->workflow($definition);

        return [
            'title' => $workflow->title(),
            'version' => $workflow->version(),
            'structure' => $workflow->structureHash(),
            'states' => count($workflow->states()),
            'transitions' => count($workflow->transitions()),
            'approvals' => count($workflow->approvals()),
            'subject' => $workflow->subjectClass(),
        ];
    }

    private function workflow(object $definition): Workflow
    {
        return $definition instanceof Workflow ? $definition : throw new InvalidArgumentException('Not a workflow.');
    }
}
