<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Workflows\Approvals\Approval;
use Gcego\Workflows\Approvals\ApprovalPolicy;
use Gcego\Workflows\Approvals\Stage;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;
use Gcego\Workflows\Scheduling\Deadline;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Support\Duration;
use Gcego\Workflows\Tests\Fixtures\Handlers\AcademicReviewers;
use Gcego\Workflows\Tests\Fixtures\Handlers\DocumentsUploaded;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Gcego\Workflows\WorkflowsConfig;

/**
 * @return list<string>
 */
function problemsOf(Workflow $workflow): array
{
    return array_map(static fn ($error): string => $error->format(), $workflow->validate()->errors());
}

it('accepts named arguments as the same definition as Property values', function (): void {
    $named = new State(name: 'approved', label: 'Approved', terminal: true, color: 'success');
    $properties = new State(new Property(State::Name, 'approved'), new Property(State::Label, 'Approved'), new Property(State::Terminal, true), new Property(State::Color, 'success'));
    $shorthand = new State('approved', 'Approved', terminal: true, color: 'success');

    expect($named->properties()->toArray())->toEqualCanonicalizing($properties->properties()->toArray())->and($shorthand->properties()->toArray())->toEqualCanonicalizing($properties->properties()->toArray())
        ->and($named->isTerminal())->toBeTrue()->and($named->color())->toBe('success')->and($named->label())->toBe('Approved');
});

it('refuses unknown named arguments', function (): void {
    new State(name: 'draft', colour: 'red');
})->throws(WorkflowDefinitionException::class, 'it has no "colour" argument');

it('reads transitions, approvals and their defaults', function (): void {
    $transition = new Transition('cancel', ['draft', 'submitted'], 'cancelled', guard: DocumentsUploaded::class, comment: 'required');
    $approval = new Approval('academic-review', 'Academic Review', state: 'under-review', requiredApprovals: 2, reviewers: AcademicReviewers::class, onApprove: 'approve');

    expect($transition->from())->toBe(['draft', 'submitted'])->and($transition->leaves('submitted'))->toBeTrue()->and($transition->leaves('approved'))->toBeFalse()
        ->and($transition->ability())->toBe('update')->and($transition->comment())->toBe('required')->and($transition->guard())->toBe(DocumentsUploaded::class)
        ->and($transition->allowsHumans())->toBeTrue()->and($transition->label())->toBe('Cancel')
        ->and($approval->stages())->toHaveCount(1)->and($approval->stages()[0]->policy())->toBe(ApprovalPolicy::Quorum)
        ->and($approval->stages()[0]->requiredApprovals())->toBe(2)->and($approval->stages()[0]->reviewers())->toBe(AcademicReviewers::class)
        ->and($approval->allowsSelfApproval())->toBeFalse()->and($approval->allowsDelegation())->toBeFalse();
});

it('validates a well-formed workflow and reports structural problems', function (): void {
    $valid = new Workflow('review', new State('draft'), new State('done', terminal: true), new Transition('finish', 'draft', 'done'));

    expect(problemsOf($valid))->toBe([])->and($valid->initialState())->toBe('draft')->and($valid->version())->toBe(1);

    $broken = new Workflow('broken',
        new State('draft'),
        new State('draft'),
        new State('done', terminal: true),
        new Transition('finish', 'draft', 'missing'),
        new Transition('again', 'done', 'draft'),
        new Transition('auto', 'draft', 'done', human: false),
        new Transition('gate', 'draft', 'done', requiresApproval: 'nowhere'),
        new Approval('check', state: 'limbo', onApprove: 'unknown'),
        initial: 'start',
    );

    expect(implode("\n", problemsOf($broken)))
        ->toContain('the state "draft" is declared twice')
        ->toContain('the initial state "start" is not declared')
        ->toContain('goes to the unknown state "missing"')
        ->toContain('leaves the terminal state "done"; mark it Reopen')
        ->toContain('"auto" allows neither people nor system actors')
        ->toContain('requires the unknown approval "nowhere"')
        ->toContain('opens in the unknown state "limbo"')
        ->toContain('names the unknown transition "unknown"');
});

it('requires deadline transitions to name the deadlines system actor', function (): void {
    $workflow = new Workflow('late',
        new State('open', new Deadline('2 days', then: 'close')),
        new State('closed', terminal: true),
        new Transition('close', 'open', 'closed'),
    );

    expect(implode("\n", problemsOf($workflow)))->toContain('must name the "deadlines" system actor');
});

it('reports unreachable states without refusing the workflow', function (): void {
    $workflow = new Workflow('island', new State('draft'), new State('done', terminal: true), new State('island'), new Transition('finish', 'draft', 'done'));

    expect(problemsOf($workflow))->toBe([])->and($workflow->unreachableStates())->toBe(['island']);
});

it('refuses invalid names, durations, colors and handler classes', function (Closure $make, string $message): void {
    expect(fn () => problemsOf($make()))->not->toThrow(Throwable::class);
    expect(implode("\n", problemsOf($make())))->toContain($message);
})->with([
    'state name' => [fn () => new Workflow('w', new State('Bad Name')), 'a lowercase name'],
    'duration' => [fn () => new Workflow('w', new State('s', new Deadline('soon'))), 'a duration'],
    'color' => [fn () => new Workflow('w', new State('s', color: 'purple')), 'purple'],
    'guard' => [fn () => new Workflow('w', new State('s'), new Transition('t', 's', 's', guard: stdClass::class)), 'implementing'],
    'quorum' => [fn () => new Workflow('w', new State('s'), new Approval('a', state: 's', requiredApprovals: 0)), 'between 1 and 50'],
]);

it('fingerprints the structure (states and transitions), not presentation', function (): void {
    $one = new Workflow('w', new State('draft', 'Draft'), new State('done', terminal: true), new Transition('finish', 'draft', 'done'));
    $relabelled = new Workflow('w', new State('draft', 'Brouillon'), new State('done', terminal: true), new Transition('finish', 'draft', 'done', label: 'Terminer'));
    $changed = new Workflow('w', new State('draft'), new State('done', terminal: true), new Transition('finish', 'draft', 'done'), new Transition('undo', 'draft', 'draft'));

    expect($relabelled->structureHash())->toBe($one->structureHash())->and($changed->structureHash())->not->toBe($one->structureHash());
});

it('builds sequential approvals from stages in order', function (): void {
    $approval = new Approval('leave', new Stage('manager'), new Stage('hr', policy: 'all'), state: 'requested');

    expect(array_map(static fn (Stage $stage): string => $stage->nodeName(), $approval->stages()))->toBe(['manager', 'hr'])
        ->and($approval->stages()[1]->policy())->toBe(ApprovalPolicy::All);
});

it('computes approvals needed per policy', function (ApprovalPolicy $policy, int $required, ?int $reviewers, int $needed): void {
    expect($policy->needed($required, $reviewers))->toBe($needed);
})->with([
    'any' => [ApprovalPolicy::Any, 3, 5, 1],
    'all assigned' => [ApprovalPolicy::All, 1, 4, 4],
    'all pool' => [ApprovalPolicy::All, 2, null, 2],
    'quorum' => [ApprovalPolicy::Quorum, 2, 3, 2],
    'majority of 4' => [ApprovalPolicy::Majority, 1, 4, 3],
    'majority of 3' => [ApprovalPolicy::Majority, 1, 3, 2],
]);

it('parses trusted durations', function (): void {
    $from = new DateTimeImmutable('2026-01-01 10:00:00');

    expect(Duration::valid('48 hours'))->toBeTrue()->and(Duration::valid('2 fortnights'))->toBeFalse()->and(Duration::valid('0 days'))->toBeFalse()
        ->and(Duration::after('48 hours', $from)->format('Y-m-d H:i'))->toBe('2026-01-03 10:00')
        ->and(Duration::after('1 week', $from)->format('Y-m-d'))->toBe('2026-01-08')
        ->and(Duration::after('30 minutes', $from)->format('H:i'))->toBe('10:30');
});

it('constructs definitions without touching the database', function (): void {
    $queries = queriesDuring(static function (): void {
        $workflow = require dirname(__DIR__).'/Fixtures/Workflows/registration.php';
        $workflow->validate();
        $workflow->structureHash();
    });

    expect($queries)->toBe([]);
});

it('validates configuration', function (array $config, string $message): void {
    expect(fn () => WorkflowsConfig::fromArray($config))->toThrow(WorkflowDefinitionException::class, $message);
})->with([
    [['approvals' => ['max_delegations' => 50]], 'max_delegations'],
    [['execution' => ['optimistic_locking' => 'yes']], 'must be true or false'],
    [['security' => ['manage_ability' => 'drop table']], 'Gate ability'],
    [['inbox' => ['per_page' => 0]], 'inbox.per_page'],
]);

it('takes any Luminaries icon name or typed icon for a state', function (): void {
    expect((new State(name: 'approved', icon: Heroicon::CheckBadge))->validate()->isValid())->toBeTrue()
        ->and((new State(name: 'approved', icon: 'status.success'))->validate()->isValid())->toBeTrue()
        ->and((new State(new Property(State::Name, 'approved'), new Property(State::Icon, 'Not An Icon')))->validate()->isValid())->toBeFalse();
});
