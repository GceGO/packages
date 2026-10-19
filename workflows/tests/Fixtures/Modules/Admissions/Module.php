<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Workflows\Columns\WorkflowStateColumn;
use Gcego\Workflows\Entries\WorkflowStateEntry;
use Gcego\Workflows\Tests\Fixtures\Actions\StartRegistration;
use Gcego\Workflows\Tests\Fixtures\Actions\SubmitRegistration;
use Gcego\Workflows\Tests\Fixtures\Actions\WithdrawRegistration;
use Gcego\Workflows\Tests\Fixtures\Models\Application;

return new Module(
    new Property(Module::Name, 'admissions'),
    new Property(Module::Title, 'Admissions'),

    new Resource(
        new Source(Application::class),
        new Property(Resource::RecordTitle, 'name'),
        new Property(Resource::RecordKey, 'id'),
        new Table(new Text('name', 'Application'), new WorkflowStateColumn('registration', 'Registration', new Property(WorkflowStateColumn::Workflow, 'registration'), new Property(WorkflowStateColumn::Eager, true))),
        new Infolist(new TextEntry('name', 'Application'), new WorkflowStateEntry('registration', 'Registration', new Property(WorkflowStateEntry::Workflow, 'registration'))),
        new Action('start-registration', 'Start registration', new Handler(StartRegistration::class), new Property(Action::Record, true), new Property(Action::Ability, 'update')),
        new Action('submit-registration', 'Submit registration', new Handler(SubmitRegistration::class), new Property(Action::Record, true), new Property(Action::Ability, 'update')),
        // Validated action input: the comment is checked before the workflow sees it.
        new Action('withdraw-registration', 'Withdraw', new Handler(WithdrawRegistration::class), new Property(Action::Record, true), new Property(Action::Ability, 'update'),
            new Input(['comment' => ['required', 'string', 'max:500']], labels: ['comment' => 'Reason']),
        ),
    ),
);
