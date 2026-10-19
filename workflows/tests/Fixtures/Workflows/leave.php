<?php

declare(strict_types=1);

use Gcego\Security\Requirements\Ability;
use Gcego\Workflows\Approvals\Approval;
use Gcego\Workflows\Approvals\Stage;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Tests\Fixtures\Handlers\Managers;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

// A sequential approval: the manager (assigned), then HR (a pool of everyone with "hr.approve").
return new Workflow(
    'leave',
    'Leave Request',
    new State('requested', 'Requested'),
    new State('revising', 'Revising'),
    new State('granted', 'Granted', terminal: true),
    new State('declined', 'Declined', terminal: true, color: 'danger'),

    new Transition('grant', 'requested', 'granted', human: false),
    new Transition('decline', 'requested', 'declined', human: false),
    new Transition('revise', 'requested', 'revising', human: false),
    new Transition('resubmit', 'revising', 'requested'),

    new Approval('leave-approval', 'Leave approval',
        new Stage('manager', 'Manager', reviewers: Managers::class),
        new Stage('hr', 'Human resources', new Ability('hr.approve')),
        state: 'requested',
        onApprove: 'grant',
        onReject: 'decline',
        onChanges: 'revise',
    ),
);
