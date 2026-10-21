<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Settings\Approvals\ApplySettingRequest;
use Gcego\Settings\Approvals\DiscardSettingRequest;
use Gcego\Settings\Approvals\SettingRequest;
use Gcego\Workflows\Automation\Automation;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

// Maker-checker: someone else who may change the setting approves it.
return new Workflow(
    'settings-approval',
    'Settings change',
    new Property(Workflow::Subject, SettingRequest::class),
    new State('pending', 'Pending'),
    new State('approved', 'Approved', terminal: true),
    new State('refused', 'Refused', terminal: true),
    new Transition('approve', 'pending', 'approved', new Automation('apply', ApplySettingRequest::class, queue: false), ability: 'approve'),
    new Transition('refuse', 'pending', 'refused', new Automation('discard', DiscardSettingRequest::class, queue: false), ability: 'approve'),
);
