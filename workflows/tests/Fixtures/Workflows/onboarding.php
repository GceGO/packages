<?php

declare(strict_types=1);

use Gcego\Security\Requirements\Ability;
use Gcego\Workflows\Automation\Automation;
use Gcego\Workflows\Scheduling\Deadline;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Tasks\Task;
use Gcego\Workflows\Tests\Fixtures\Handlers\CompleteOnboarding;
use Gcego\Workflows\Tests\Fixtures\Handlers\FlakyHandler;
use Gcego\Workflows\Tests\Fixtures\Handlers\Supervisors;
use Gcego\Workflows\Tests\Fixtures\Handlers\Verifiers;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

// Human tasks (assigned and pool), queued automation and an escalating deadline.
return new Workflow(
    'onboarding',
    'Onboarding',
    new State('setup', 'Setup',
        new Task('verify-documents', 'Verify documents', assign: Verifiers::class, due: '2 days', priority: 'high', completes: 'verified'),
        new Task('prepare-room', 'Prepare the room', new Ability('facilities.manage')),
        new Deadline('5 days', escalate: Supervisors::class),
        new Automation('score', FlakyHandler::class),
    ),
    new State('ready', 'Ready', new Automation('auto-complete', CompleteOnboarding::class)),
    new State('done', 'Done', terminal: true),

    new Transition('verified', 'setup', 'ready', human: false),
    new Transition('finish', 'ready', 'done', human: false, systemActors: ['automation']),
    new Transition('skip', 'setup', 'done'),

    concurrent: true,
);
