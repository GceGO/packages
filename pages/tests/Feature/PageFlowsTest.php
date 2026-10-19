<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Pages\Livewire\PageHost;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Security\SecurityManager;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('creates a record through the form and the Create action, then opens it', function (): void {
    actAs($this->world['editor']);
    $form = Livewire::test(FormHost::class, ['definition' => 'page:subjects-create'])
        ->set('data.name', 'Biology')
        ->set('data.code', 'BIO1')
        ->call('submit')
        ->assertDispatched('gcego-action-completed');
    $subject = Subject::query()->where('code', 'BIO1')->firstOrFail();
    $result = ['status' => 'success', 'action' => 'page:subjects-create/action:create', 'record_key' => $subject->getKey(), 'data' => []];

    expect($form->get('action'))->toBe('create')
        ->and($subject->getAttribute('institution_id'))->toBe(1)
        ->and($subject->getAttribute('internal_notes'))->toBeNull();

    Livewire::test(PageHost::class, ['page' => 'subjects.create'])
        ->dispatch('gcego-action-completed', result: $result)
        ->assertRedirect('/admin/subjects/'.$subject->getKey());
});

it('validates through gcego/forms and saves nothing on errors', function (): void {
    actAs($this->world['editor']);

    Livewire::test(FormHost::class, ['definition' => 'page:subjects-create'])
        ->set('data.name', '')
        ->call('submit')
        ->assertHasErrors(['data.name', 'data.code'])
        ->assertNotDispatched('gcego-action-completed');

    expect(Subject::query()->count())->toBe(4);
});

it('never mass-assigns undeclared fields', function (): void {
    actAs($this->world['editor']);

    expect(fn () => Livewire::test(FormHost::class, ['definition' => 'page:subjects-create'])->set('data.internal_notes', 'pwned'))->toThrow(InvalidStateException::class);
});

it('edits a record in scope and stays on the page', function (): void {
    actAs($this->world['editor']);

    Livewire::test(FormHost::class, ['definition' => 'page:subjects-edit', 'record' => 1])
        ->assertSet('data.name', 'Chemistry')
        ->set('data.name', 'Chemistry II')
        ->call('submit')
        ->assertDispatched('gcego-action-completed');

    expect(Subject::query()->findOrFail(1)->getAttribute('name'))->toBe('Chemistry II');

    Livewire::test(PageHost::class, ['page' => 'subjects.edit', 'record' => 1])
        ->dispatch('gcego-action-completed', result: ['status' => 'success', 'action' => 'page:subjects-edit/action:edit', 'record_key' => 1])
        ->assertNoRedirect()
        ->assertSee('Edit Chemistry II');
});

it('refuses the edit form for records out of scope or denied by the policy', function (string $user, int $record): void {
    actAs($this->world[$user]);

    Livewire::test(FormHost::class, ['definition' => 'page:subjects-edit', 'record' => $record])->assertForbidden();
})->with([
    'reader: policy' => ['reader', 1],
    'locked record' => ['admin', 3],
]);

it('goes back to the list after deleting the record of a record page', function (): void {
    actAs($this->world['admin']);
    $actions = app(ActionManager::class);
    $pending = $actions->execute(new ActionRequest('page:subjects-view', 'delete', record: 2));
    $result = $actions->execute(new ActionRequest('page:subjects-view', 'delete', record: 2, confirmation: $pending->confirmation['token'] ?? null));

    expect($pending->status)->toBe(ActionStatus::ConfirmationRequired)
        ->and($result->status)->toBe(ActionStatus::Success)
        ->and(Subject::query()->find(2))->toBeNull();

    Livewire::test(PageHost::class, ['page' => 'subjects.view', 'record' => 1])
        ->dispatch('gcego-action-completed', result: [...$result->toArray(), 'record_key' => '1'])
        ->assertRedirect('/admin/subjects');
});

it('opens the view page for a row "view" action of the index page', function (): void {
    actAs($this->world['admin']);
    $result = app(ActionManager::class)->execute(new ActionRequest('page:subjects', 'view', record: 1));

    expect($result->status)->toBe(ActionStatus::Success);

    Livewire::test(PageHost::class, ['page' => 'subjects'])
        ->dispatch('gcego-action-completed', result: $result->toArray())
        ->assertRedirect('/admin/subjects/1');
});

it('only follows results of its own actions, to targets the definition allows', function (mixed $result, ?string $redirect): void {
    actAs($this->world['admin']);
    $host = Livewire::test(PageHost::class, ['page' => 'subjects.create'])->dispatch('gcego-action-completed', result: $result);

    $redirect === null ? $host->assertNoRedirect() : $host->assertRedirect($redirect);
})->with([
    'another page' => [['status' => 'success', 'action' => 'page:subjects-edit/action:edit', 'record_key' => 1], null],
    'a failed action' => [['status' => 'failed', 'action' => 'page:subjects-create/action:create', 'record_key' => 1], null],
    'not an array' => ['https://evil.test', null],
    // A forged "created" result can only lead to a page the user may already open: never to the key it names.
    'an unsafe key' => [['status' => 'success', 'action' => 'page:subjects-create/action:create', 'record_key' => '../../etc'], '/admin/subjects'],
    'a key of another tenant' => [['status' => 'success', 'action' => 'page:subjects-create/action:create', 'record_key' => 4], '/admin/subjects'],
    'a forged redirect' => [['status' => 'success', 'action' => 'page:subjects-create/action:create', 'redirect' => 'https://evil.test'], '/admin/subjects'],
]);

it('keeps page state when the record title changes', function (): void {
    actAs($this->world['admin']);
    $host = Livewire::test(PageHost::class, ['page' => 'subjects.view', 'record' => 1])->assertSee('Chemistry');
    Subject::query()->whereKey(1)->update(['name' => 'Renamed']);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant('1');

    $host->call('$refresh')->assertSee('Renamed')->assertSet('record', '1')->assertSet('page', 'page:subjects-view');
});
