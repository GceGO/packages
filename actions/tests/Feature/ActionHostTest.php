<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Livewire\ActionHost;
use Gcego\Actions\Tests\Fixtures\Models\Membership;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

function host(int|string|null $record = null, ?string $placement = null): Testable
{
    return Livewire::test(ActionHost::class, ['definition' => 'subjects', 'placement' => $placement, 'record' => $record]);
}

it('mounts with the canonical identity and renders only available actions', function (): void {
    actAs($this->world['reader']);
    $component = host($this->world['subjects']['algebra']->id, 'row');

    expect($component->get('definition'))->toBe('blueprint:subjects');
    $component->assertSee('View')->assertDontSee('Edit')->assertDontSee('Delete')->assertDontSee('Secret')
        ->assertSeeHtml('wire:loading.attr="disabled"');

    actAs($this->world['admin']);
    host($this->world['subjects']['algebra']->id, 'row')->assertSee('Edit')->assertSee('Delete')->assertSeeHtml('data-gcego-action="delete"');
});

it('renders disabled actions disabled and never shows hidden ones', function (): void {
    actAs($this->world['admin']);

    host()->assertSeeHtml('data-gcego-action="frozen"')->assertDontSeeHtml('data-gcego-action="secret"');
    expect(host()->html())->toMatch('/data-gcego-action="frozen"[^>]*disabled/');
});

it('opens a confirmation dialog, cancels it, and executes on confirm', function (): void {
    actAs($this->world['admin']);
    $biology = $this->world['subjects']['biology'];
    $component = host($biology->id, 'row');

    $component->call('run', 'delete')
        ->assertSet('dialog.action', 'delete')
        ->assertSet('dialog.destructive', true)
        ->assertSeeHtml('role="alertdialog"')
        ->assertSeeHtml('aria-modal="true"')
        ->call('cancel')
        ->assertSet('dialog', null);

    expect(Subject::query()->find($biology->id))->not->toBeNull();

    $component->call('run', 'delete')->call('submit')
        ->assertSet('dialog', null)
        ->assertSet('feedback.status', 'success')
        ->assertDispatched('gcego-action-completed');

    expect(Subject::query()->find($biology->id))->toBeNull();
});

it('ignores duplicate submissions of the same confirmation', function (): void {
    actAs($this->world['admin']);
    $biology = $this->world['subjects']['biology'];
    $component = host($biology->id, 'row')->call('run', 'delete');
    $dialog = $component->get('dialog');

    $component->call('submit')->assertSet('feedback.status', 'success');
    $biology->restore();

    // A second submit has no dialog any more; replaying the old token through a new host is refused.
    $component->call('submit')->assertSet('feedback.status', 'success');
    expect(Subject::query()->find($biology->id))->not->toBeNull();

    $replay = ActionRequest::class;
    expect(app(ActionManager::class)->execute(new $replay('blueprint:subjects', 'delete', $biology->id, confirmation: $dialog['token']))->reason)->toBe('confirmation_invalid');
});

it('collects input, shows validation errors and saves valid input', function (): void {
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];
    $component = host($algebra->id, 'row')->call('run', 'edit');

    $component->assertSet('dialog.mode', 'input')
        ->assertSet('input.name', 'Algebra')
        ->assertSeeHtml('wire:model="input.name"')
        ->set('input.name', '')
        ->call('submit')
        ->assertHasErrors(['input.name'])
        ->assertSet('dialog.action', 'edit')
        ->assertSeeHtml('aria-invalid="true"')
        ->set('input.name', 'Linear Algebra')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('dialog', null);

    expect($algebra->refresh()->name)->toBe('Linear Algebra');
});

it('asks for confirmation after input when an action needs both', function (): void {
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];

    host($algebra->id)->call('run', 'rename')->set('input.name', 'Two-step')->call('submit')
        ->assertSet('dialog.mode', 'confirm')
        ->assertSet('input.name', 'Two-step')
        ->call('submit')
        ->assertSet('dialog', null);

    expect($algebra->refresh()->name)->toBe('Two-step');
});

it('rejects undeclared fields set through public state', function (): void {
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];

    host($algebra->id, 'row')->call('run', 'edit')->set('input.institution_id', 2)->call('submit')->assertSet('feedback.status', 'denied');

    expect($algebra->refresh()->institution_id)->toBe(1);
});

it('locks the definition, record, scope and dialog against client changes', function (string $property, mixed $value): void {
    actAs($this->world['admin']);

    expect(fn () => host($this->world['subjects']['algebra']->id, 'row')->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    ['definition', 'blueprint:reports'],
    ['record', 4],
    ['placement', null],
    ['names', ['force-delete']],
    ['dialog', ['action' => 'force-delete', 'mode' => 'confirm', 'token' => 'x', 'title' => '', 'message' => '', 'confirm' => '', 'cancel' => '', 'destructive' => true, 'fields' => [], 'selection' => []]],
]);

it('refuses actions outside the host scope or definition', function (): void {
    actAs($this->world['admin']);

    host($this->world['subjects']['algebra']->id, 'row')->call('run', 'create')->assertSet('feedback.status', 'not_found');
    host()->call('run', 'export')->assertSet('feedback.status', 'not_found');
    expect(fn () => host()->call('handle', 'delete'))->toThrow(MethodNotFoundException::class);
});

it('re-authorizes every interaction, including after a tenant change', function (): void {
    actAs($this->world['admin']);
    $component = host($this->world['subjects']['algebra']->id, 'row')->call('run', 'delete');

    // The next request acts for institution 2, where the record does not exist.
    actAs($this->world['admin'], 2);
    $component->call('submit')->assertSet('feedback.status', 'not_found');

    expect(Subject::query()->find($this->world['subjects']['algebra']->id))->not->toBeNull();
});

it('denies a user whose membership ended between interactions', function (): void {
    actAs($this->world['editor']);
    $component = host(placement: 'header')->call('run', 'create')->set('input.name', 'Late');
    Membership::query()->where('user_id', $this->world['editor']->id)->update(['status' => 'ended']);
    app()->forgetScopedInstances();

    $component->call('submit')->assertSet('feedback.status', 'denied');
    expect(Subject::query()->where('name', 'Late')->exists())->toBeFalse();
});

it('runs bulk actions for client selections through the scoped source', function (): void {
    actAs($this->world['admin']);
    $keys = [$this->world['subjects']['algebra']->id, $this->world['subjects']['south']->id];

    host(placement: 'bulk')->call('runBulk', 'bulk-delete', $keys)->assertSet('feedback.status', 'denied');

    $component = host(placement: 'bulk')->call('runBulk', 'bulk-delete', [$this->world['subjects']['algebra']->id])->assertSet('dialog.action', 'bulk-delete');
    $component->call('submit')->assertSet('feedback.status', 'success');

    expect(Subject::query()->find($this->world['subjects']['algebra']->id))->toBeNull();
});
