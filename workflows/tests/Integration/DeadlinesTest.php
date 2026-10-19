<?php

declare(strict_types=1);

use Gcego\Workflows\Approvals\ApprovalCoordinator;
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Console\ProcessDeadlinesCommand;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Tasks\TaskStatus;
use Illuminate\Console\Scheduling\Schedule;

it('runs a state deadline once, as the deadlines system actor', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);

    $this->artisan('gcego:workflows:process-deadlines')->assertSuccessful();
    expect($instance->refresh()->state)->toBe('submitted');

    $this->travel(8)->days();
    $this->artisan('gcego:workflows:process-deadlines')->expectsOutputToContain('Deadlines: 1')->assertSuccessful();

    $expired = $instance->events()->where('type', 'workflow.transitioned')->where('to_state', 'expired')->first();
    expect($instance->refresh()->state)->toBe('expired')->and($expired?->actor_name)->toBe('deadlines')
        ->and(historyOf($instance))->toContain('deadline.reached');

    $this->artisan('gcego:workflows:process-deadlines')->expectsOutputToContain('Deadlines: 0')->assertSuccessful();
    expect($instance->events()->where('type', 'deadline.reached')->count())->toBe(1);
});

it('waits with the deadlines of suspended instances', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);
    actAs($world['admin']);
    engine()->suspend($instance, $world['admin']);

    $this->travel(8)->days();
    $this->artisan('gcego:workflows:process-deadlines')->assertSuccessful();

    expect($instance->refresh()->state)->toBe('submitted');
});

it('marks overdue approvals once, notifies the reviewer and escalates within the tenant', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Approve);
    $task = taskOf($instance, $world['reviewer2']);

    $this->travel(4)->days();
    $this->artisan('gcego:workflows:process-deadlines')->assertSuccessful();

    $escalated = taskOf($instance, $world['admin']);
    expect($task->refresh()->status)->toBe(TaskStatus::Escalated)->and($task->overdue_at)->not->toBeNull()
        ->and($escalated->priority)->toBe('high')
        ->and(WorkflowTask::query()->where('assignee_id', (string) $world['south']->id)->exists())->toBeFalse()
        ->and($world['reviewer2']->notifications()->where('data->title', 'gcego-workflows::messages.notifications.overdue.title')->exists())->toBeTrue()
        ->and(historyOf($instance->refresh()))->toContain('task.overdue', 'task.escalated');

    $this->artisan('gcego:workflows:process-deadlines')->assertSuccessful();
    expect($instance->events()->where('type', 'task.overdue')->count())->toBe(2);

    decideAs($world['admin'], $instance, Decision::Approve);
    expect(app(ApprovalCoordinator::class)->outcome($instance->refresh(), 'academic-review'))->toBe('approved');
});

it('escalates the open tasks of a state when its deadline passes', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);

    $this->travel(6)->days();
    $this->artisan('gcego:workflows:process-deadlines')->assertSuccessful();

    // The pool task needs "facilities.manage", which the supervisor lacks: escalation never widens access.
    expect(WorkflowTask::query()->where('instance_id', $instance->id)->open()->where('assignee_id', (string) $world['admin']->id)->pluck('name')->all())->toBe(['verify-documents'])
        ->and(taskOf($instance, null, 'prepare-room')->pool)->toBeTrue()
        ->and($instance->events()->where('type', 'task.escalated')->get()->map(fn ($event) => $event->metadata['targets'] ?? null)->sort()->values()->all())->toBe([0, 1])
        ->and($instance->refresh()->deadline_at)->toBeNull()->and($instance->state)->toBe('setup');
});

it('schedules deadline processing with Laravel\'s scheduler unless disabled', function (): void {
    $commands = static fn (): array => collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command)->filter(fn (string $command) => str_contains($command, 'gcego:workflows:process-deadlines'))->values()->all();

    expect($commands())->toHaveCount(1);

    $this->reboot(['gcego-workflows.deadlines.schedule' => false]);
    expect($commands())->toBe([]);

    $this->reboot(['gcego-workflows.deadlines.enabled' => false]);
    $this->artisan(ProcessDeadlinesCommand::class)->expectsOutputToContain('disabled')->assertSuccessful();
});
