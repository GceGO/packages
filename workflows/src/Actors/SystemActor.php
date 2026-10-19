<?php

declare(strict_types=1);

namespace Gcego\Workflows\Actors;

use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;

/**
 * Trusted automation acting on a workflow: "deadlines" (the scheduler),
 * "automation" (handlers), "approvals" (approval outcomes), "tasks" (task
 * completion), or an application name. It is not a user and has no
 * permissions of its own: it may only run transitions that name it
 * (approval and task outcomes are implied by the definition), and it is
 * recorded as such in the history. Only server code creates one.
 */
final readonly class SystemActor
{
    public const string Deadlines = 'deadlines';

    public const string Automation = 'automation';

    public const string Approvals = 'approvals';

    public const string Tasks = 'tasks';

    public function __construct(public string $name)
    {
        if (! WorkflowNode::validName($name)) {
            throw WorkflowDefinitionException::invalid('System actor "'.mb_substr($name, 0, 40).'"', 'use a lowercase name such as "deadlines".');
        }
    }

    public static function named(string $name): self
    {
        return new self($name);
    }
}
