<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Livewire\ActionHost;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\Tests\Fixtures\Models\Document;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function dispatcher(string $channel = 'table-1', string $definition = 'subjects'): Testable
{
    return Livewire::test(ActionHost::class, ['definition' => $definition, 'channel' => $channel, 'showButtons' => false]);
}

it('offers row actions per record and permission without extra queries per row', function (): void {
    $html = Livewire::test(TableHost::class, ['definition' => 'subjects'])->html();

    expect(substr_count($html, "runAction('edit'"))->toBe(4)
        ->and(substr_count($html, "runAction('delete'"))->toBe(4)
        ->and($html)->not->toContain("runAction('restore'")
        ->and($html)->not->toContain("runAction('archive'")
        ->and($html)->toContain('data-gcego-actions="blueprint:subjects"');

    actAs($this->world['reader']);
    $reader = Livewire::test(TableHost::class, ['definition' => 'subjects'])->html();

    expect($reader)->not->toContain("runAction('edit'")
        ->and($reader)->not->toContain("runAction('delete'")
        ->and($reader)->toContain("runAction('view'");

    actAs($this->world['admin']);

    expect(Livewire::test(TableHost::class, ['definition' => 'subjects'])->html())->toContain("runAction('archive'");
});

it('shows the header Create action and bulk actions only when permitted', function (): void {
    expect(Livewire::test(TableHost::class, ['definition' => 'subjects'])->html())->toContain('data-gcego-action="create"');

    $html = Livewire::test(TableHost::class, ['definition' => 'subjects'])->set('selected', [(string) $this->world['subjects']['algebra']->id])->html();

    expect($html)->toContain('data-gcego-bulk-bar')
        ->and($html)->toContain("runBulkAction('bulk-delete')");

    actAs($this->world['reader']);

    expect(Livewire::test(TableHost::class, ['definition' => 'subjects'])->set('selected', ['1'])->html())->not->toContain("runBulkAction('bulk-delete')");
});

it('sends row and bulk requests on the table\'s own channel', function (): void {
    $host = Livewire::test(TableHost::class, ['definition' => 'subjects']);
    $id = (string) $this->world['subjects']['algebra']->id;

    $host->call('runAction', 'edit', $id)->assertDispatched('gcego-actions-run', channel: $host->id(), action: 'edit', record: $id);
    $host->set('selected', [$id])->call('runBulkAction', 'bulk-delete')->assertDispatched('gcego-actions-run', channel: $host->id(), action: 'bulk-delete', keys: [$id]);
    $host->set('selected', [])->call('runBulkAction', 'bulk-delete')->assertSee('Select at least one row first.');
});

it('opens the Edit form for the requested row and saves through gcego/actions', function (): void {
    $algebra = $this->world['subjects']['algebra'];
    $actions = dispatcher()->dispatch('gcego-actions-run', channel: 'table-1', action: 'edit', record: (string) $algebra->id);

    expect($actions->get('record'))->toBe((string) $algebra->id)
        ->and($actions->html())->toContain('wire:model="input.name"');

    $actions->set('input.name', 'Linear Algebra')->call('submit')->assertDispatched('gcego-action-completed');

    expect($algebra->refresh()->name)->toBe('Linear Algebra');
});

it('ignores requests for another channel', function (): void {
    $actions = dispatcher('table-1')->dispatch('gcego-actions-run', channel: 'table-2', action: 'delete', record: (string) $this->world['subjects']['algebra']->id);

    expect($actions->get('dialog'))->toBeNull()
        ->and(Subject::query()->where('institution_id', 1)->count())->toBe(4);
});

it('asks to confirm deletes and refuses records outside the tenant', function (): void {
    $algebra = $this->world['subjects']['algebra'];
    $actions = dispatcher()->dispatch('gcego-actions-run', channel: 'table-1', action: 'delete', record: (string) $algebra->id);

    expect($actions->get('dialog.mode'))->toBe('confirm');

    $actions->call('submit');

    expect(Subject::query()->whereKey($algebra->id)->exists())->toBeFalse()
        ->and(Subject::withTrashed()->whereKey($algebra->id)->exists())->toBeTrue();

    $south = dispatcher()->dispatch('gcego-actions-run', channel: 'table-1', action: 'delete', record: (string) $this->world['south']->id);

    expect($south->get('dialog'))->toBeNull()
        ->and($south->get('feedback.status'))->toBeIn(['not_found', 'denied'])
        ->and(Subject::query()->withoutGlobalScopes()->whereKey($this->world['south']->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('refuses a bulk selection containing out-of-scope or forged keys, all or nothing', function (): void {
    $keys = [(string) $this->world['subjects']['algebra']->id, (string) $this->world['subjects']['biology']->id, (string) $this->world['south']->id, '999999'];
    $actions = dispatcher()->dispatch('gcego-actions-run', channel: 'table-1', action: 'bulk-delete', keys: $keys);
    $actions->call('submit');

    expect($actions->get('feedback.status'))->toBe('denied')
        ->and(Subject::query()->where('institution_id', 1)->count())->toBe(4)
        ->and(Subject::query()->whereKey($this->world['south']->id)->exists())->toBeTrue();
});

it('runs bulk actions on a clean, in-scope selection', function (): void {
    $keys = [(string) $this->world['subjects']['algebra']->id, (string) $this->world['subjects']['biology']->id];
    $actions = dispatcher()->dispatch('gcego-actions-run', channel: 'table-1', action: 'bulk-delete', keys: $keys);

    expect($actions->get('dialog.mode'))->toBe('confirm');

    $actions->call('submit')->assertDispatched('gcego-action-completed');

    expect(Subject::query()->where('institution_id', 1)->pluck('name')->all())->toEqualCanonicalizing(['Chemistry', 'Drama']);
});

it('clears the selection after a successful action', function (): void {
    Livewire::test(TableHost::class, ['definition' => 'subjects'])
        ->set('selected', ['1', '2'])
        ->dispatch('gcego-action-completed', result: ['status' => 'success'])
        ->assertSet('selected', []);
});

it('offers Restore only for trashed rows of sources that include them', function (): void {
    $algebra = $this->world['subjects']['algebra'];
    $algebra->delete();

    expect(app(ActionManager::class)->allowsLoaded(app(ActionManager::class)->resolve('subjects', 'restore'), Subject::withTrashed()->find($algebra->id)))->toBeTrue()
        ->and(app(ActionManager::class)->allowsLoaded(app(ActionManager::class)->resolve('subjects', 'edit'), Subject::withTrashed()->find($algebra->id)))->toBeFalse()
        ->and(app(ActionManager::class)->allowsLoaded(app(ActionManager::class)->resolve('subjects', 'restore'), $this->world['subjects']['biology']))->toBeFalse();
});

it('selects and bulk-deletes UUID-keyed rows', function (): void {
    $first = Document::query()->create(['title' => 'Policy', 'institution_id' => 1]);
    $second = Document::query()->create(['title' => 'Guide', 'institution_id' => 1]);
    $foreign = Document::query()->create(['title' => 'Foreign', 'institution_id' => 2]);

    $host = Livewire::test(TableHost::class, ['definition' => 'documents'])->call('selectPage');

    expect($host->get('selected'))->toEqualCanonicalizing([$first->id, $second->id]);

    $actions = dispatcher('t', 'documents')->dispatch('gcego-actions-run', channel: 't', action: 'bulk-delete', keys: [$first->id, $foreign->id]);
    $actions->call('submit');

    expect(Document::query()->count())->toBe(3);

    $actions = dispatcher('t', 'documents')->dispatch('gcego-actions-run', channel: 't', action: 'bulk-delete', keys: [$first->id]);
    $actions->call('submit');

    expect(Document::query()->pluck('title')->all())->toEqualCanonicalizing(['Guide', 'Foreign']);
});
