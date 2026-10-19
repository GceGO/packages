<?php

declare(strict_types=1);

use Gcego\Workflows\Approvals\Approval;
use Gcego\Workflows\Automation\Automation;
use Gcego\Workflows\Scheduling\Deadline;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Tests\Fixtures\Handlers\AcademicReviewers;
use Gcego\Workflows\Tests\Fixtures\Handlers\AnnouncePublication;
use Gcego\Workflows\Tests\Fixtures\Handlers\DocumentsUploaded;
use Gcego\Workflows\Tests\Fixtures\Handlers\IncompleteApplication;
use Gcego\Workflows\Tests\Fixtures\Handlers\Supervisors;
use Gcego\Workflows\Tests\Fixtures\Models\Application;
use Gcego\Workflows\Transitions\Branch;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

// A parallel quorum approval (2 of 3 reviewers) opened in "under-review".
return new Workflow(
    new State('draft', 'Draft', color: 'neutral'),
    new State('submitted', 'Submitted', new Deadline('7 days', then: 'expire')),
    new State('under-review', 'Under review', color: 'warning'),
    new State('returned', 'Returned', color: 'warning'),
    new State('approved', 'Approved', color: 'success'),
    new State('rejected', 'Rejected', terminal: true, color: 'danger'),
    new State('expired', 'Expired', terminal: true, color: 'neutral'),
    new State('published', 'Published', terminal: true),

    new Transition('submit', ['draft', 'returned'], 'submitted', guard: DocumentsUploaded::class),
    new Transition('review', 'submitted', 'under-review', new Branch('returned', IncompleteApplication::class), ability: 'review'),
    new Transition('accept', 'under-review', 'approved', ability: 'review', requiresApproval: 'academic-review'),
    new Transition('reject', 'under-review', 'rejected', human: false),
    new Transition('return', 'under-review', 'returned', human: false),
    new Transition('expire', 'submitted', 'expired', human: false, systemActors: ['deadlines']),
    new Transition('publish', 'approved', 'published', new Automation('announce', AnnouncePublication::class, queue: false), confirmation: true, comment: 'required'),
    new Transition('reopen', 'rejected', 'draft', reopen: true),
    new Transition('withdraw', ['draft', 'submitted', 'returned'], 'expired', comment: 'required'),

    new Approval('academic-review', 'Academic review',
        state: 'under-review',
        reviewers: AcademicReviewers::class,
        policy: 'quorum',
        requiredApprovals: 2,
        onReject: 'reject',
        onChanges: 'return',
        due: '3 days',
        escalate: Supervisors::class,
        abstain: true,
        delegation: true,
    ),

    name: 'registration',
    title: 'Institution Registration',
    version: 2,
    compatible: [1],
    subject: Application::class,
);
