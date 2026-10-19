<?php

declare(strict_types=1);

use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Livewire\WorkflowApproval;
use Gcego\Workflows\Livewire\WorkflowInbox;
use Gcego\Workflows\Livewire\WorkflowPanel;
use Gcego\Workflows\Livewire\WorkflowTimeline;
use Gcego\Workflows\Models\WorkflowTask;
use Livewire\Livewire;

it('shows the state and the transitions the user may run, and runs them', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    Livewire::actingAs($world['editor'])->withQueryParams([])
        ->test(WorkflowPanel::class, ['instance' => $instance->ulid])
        ->assertSee('Draft')->assertSee('Submit')->assertSee('Withdraw')->assertDontSee('Publish')
        ->call('runTransition', 'submit')
        ->assertHasNoErrors()->assertSee('Moved to Submitted.')->assertSet('version', $instance->refresh()->lock_version);

    expect($instance->state)->toBe('submitted');
});

it('starts a workflow for a subject from the panel', function (): void {
    $world = world();
    actAs($world['editor']);

    $component = Livewire::actingAs($world['editor'])->test(WorkflowPanel::class, ['subject' => $world['alpha'], 'workflow' => 'registration'])
        ->assertSee('Start')->call('start')->assertSee('The workflow has started.');

    expect(engine()->running('registration', $world['alpha'])?->ulid)->toBe($component->get('instance'));

    actAs($world['visitor']);
    Livewire::actingAs($world['visitor'])->test(WorkflowPanel::class, ['subject' => $world['beta'], 'workflow' => 'registration'])
        ->assertDontSee('>Start<', false)->call('start')->assertHasErrors('workflow');
});

it('tells the user when the workflow changed since they loaded it', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    $component = Livewire::actingAs($world['editor'])->test(WorkflowPanel::class, ['instance' => $instance->ulid]);

    actAs($world['admin']);
    engine()->suspend($instance, $world['admin']);
    engine()->resume($instance, $world['admin']);

    $component->call('runTransition', 'submit')->assertHasErrors('workflow')->assertSee('This workflow changed while you were looking at it.');
    expect($instance->refresh()->state)->toBe('draft');

    $component->call('runTransition', 'submit')->assertHasNoErrors();
    expect($instance->refresh()->state)->toBe('submitted');
});

it('shows guard reasons on disabled transitions', function (): void {
    $world = world();
    $world['alpha']->forceFill(['documents' => false])->save();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    Livewire::actingAs($world['editor'])->test(WorkflowPanel::class, ['instance' => $instance->ulid])
        ->assertSeeHtml('aria-describedby="gcego-wf-reason-')->assertSee('Upload the registration documents first.');
});

it('decides approvals from the approval component, with the user\'s own identity', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);
    actAs($world['reviewer1']);

    Livewire::actingAs($world['reviewer1'])->test(WorkflowApproval::class, ['task' => $task->ulid])
        ->assertSee('Approve')->assertSee('Request changes')->assertSee('Abstain')
        ->call('decide', 'reject')->assertHasErrors('approval')->assertSee('Please add a comment.')
        ->set('comment', 'Looks right.')->call('decide', 'approve')->assertHasNoErrors()->assertDispatched('gcego-workflow-updated');

    expect($task->refresh()->isOpen())->toBeFalse();

    actAs($world['reviewer2']);
    Livewire::actingAs($world['reviewer2'])->test(WorkflowApproval::class, ['task' => $task->ulid])->assertDontSee('Approve');
});

it('offers delegation only to the approval\'s eligible reviewers', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);
    actAs($world['reviewer1']);

    $component = Livewire::actingAs($world['reviewer1'])->test(WorkflowApproval::class, ['task' => $task->ulid])
        ->assertSee('Pass on to')->assertSee('Reviewer2')->assertDontSee('South');

    $component->set('delegateTo', $world['south']->getMorphClass().'#'.$world['south']->id)->call('delegate')->assertHasErrors('approval');
    $component->set('delegateTo', $world['reviewer2']->getMorphClass().'#'.$world['reviewer2']->id)->call('delegate')->assertHasErrors('approval');

    expect($task->refresh()->isOpen())->toBeTrue();
});

it('lists the inbox, filters it and lets people claim pool tasks', function (): void {
    $world = world();
    actAs($world['editor']);
    engine()->start('onboarding', $world['alpha'], $world['editor']);
    inReview($world);
    actAs($world['reviewer3']);

    $inbox = Livewire::actingAs($world['reviewer3'])->test(WorkflowInbox::class)
        ->assertSee('Academic review')->assertSee('Prepare the room')->assertSee('Open to your team')->assertDontSee('Verify documents');

    $inbox->set('filter', 'approvals')->assertSee('Academic review')->assertDontSee('Prepare the room');
    $inbox->set('filter', 'nonsense')->assertSet('filter', 'all');

    $room = WorkflowTask::query()->where('name', 'prepare-room')->firstOrFail();
    $inbox->call('claim', $room->ulid)->assertSee('The task is yours.');

    expect($room->refresh()->isAssignedTo($world['reviewer3']))->toBeTrue();

    actAs($world['visitor']);
    Livewire::actingAs($world['visitor'])->test(WorkflowInbox::class)->assertSee('Nothing is waiting for you.');
});

it('renders the history newest first, escaping comments', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Changes, '<script>alert("x")</script> Add the plan.');
    actAs($world['reviewer2']);

    Livewire::actingAs($world['reviewer2'])->test(WorkflowTimeline::class, ['instance' => $instance->ulid])
        ->assertSee('Academic review: Request changes')->assertSee('System (approvals)')->assertSee('Reviewer1')
        ->assertDontSeeHtml('<script>alert')->assertSeeHtml('&lt;script&gt;');
});

it('never trusts locked properties sent back by the browser', function (): void {
    $world = world();
    actAs($world['editor']);
    $mine = engine()->start('registration', $world['alpha'], $world['editor']);

    $page = html($this->actingAs($world['editor'])->withSession(tenantSession())->get('/desk/workflows/'.$mine->ulid)->assertOk());
    $snapshot = snapshotOf($page, 'gcego-workflow-panel');
    $tampered = str_replace($mine->ulid, '01ARZ3NDEKTSV4RRFFQ69G5FAV', $snapshot);

    livewireUpdate($tampered, [['method' => 'runTransition', 'params' => ['submit']]])->assertStatus(419);
    livewireUpdate($snapshot, [], ['version' => 999])->assertStatus(419);
    expect($mine->refresh()->state)->toBe('draft');

    livewireUpdate($snapshot, [['method' => 'runTransition', 'params' => ['submit']]])->assertOk();
    expect($mine->refresh()->state)->toBe('submitted');
});
