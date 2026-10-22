<?php

declare(strict_types=1);

use Gcego\Actions\Livewire\ActionHost;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Livewire\InfolistHost;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Rendering\Renderers\RendererRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function infolistHost(string|int $record, string $definition = 'subjects'): Testable
{
    return Livewire::test(InfolistHost::class, ['definition' => $definition, 'record' => $record]);
}

it('mounts for an authorized record and shows record actions', function (): void {
    $host = infolistHost($this->world['subject']->id);

    expect($host->html())->toContain('Algebra')
        ->and($host->html())->toContain('wire:name="gcego-actions"')
        ->and($host->html())->toContain('data-gcego-action="edit"')
        ->and($host->html())->toContain('data-gcego-action="delete"')
        ->and($host->html())->not->toContain('data-gcego-action="restore"');
});

it('refuses to mount missing, cross-tenant or unauthorized records', function (): void {
    infolistHost(999999)->assertStatus(404);
    infolistHost($this->world['south']->id)->assertStatus(404);

    Gate::before(fn ($user, string $ability) => $ability === 'view' ? false : null);

    infolistHost($this->world['subject']->id)->assertForbidden();
});

it('keeps only the definition and record key in the snapshot', function (): void {
    Gate::define('subjects.secrets', fn (): bool => true);
    actAs($this->world['editor']);
    $snapshot = (string) json_encode(infolistHost($this->world['subject']->id)->snapshot['data']);

    expect($snapshot)->not->toContain('TOP SECRET')
        ->and($snapshot)->not->toContain('Algebra')
        ->and($snapshot)->not->toContain('tok_live')
        ->and($snapshot)->toContain('blueprint:subjects');
});

it('locks the definition and record', function (string $property, mixed $value): void {
    expect(fn () => infolistHost($this->world['subject']->id)->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([['definition', 'minimal'], ['record', 2]]);

it('re-reads the record and access on every request', function (): void {
    $host = infolistHost($this->world['subject']->id);
    $this->world['subject']->update(['name' => 'Linear Algebra']);

    expect($host->call('refresh')->html())->toContain('Linear Algebra');

    Gate::define('subjects.secrets', fn (): bool => true);
    actAs($this->world['editor']);

    expect($host->call('refresh')->html())->toContain('TOP SECRET');

    Gate::before(fn ($user, string $ability) => $ability === 'view' ? false : null);
    actAs($this->world['editor']);

    expect($host->call('refresh')->html())->toContain('This record is not available.')
        ->and($host->html())->not->toContain('Linear Algebra');
});

it('deletes through the record Delete action; the archive view still shows the trashed record', function (): void {
    $id = $this->world['subject']->id;
    $actions = Livewire::test(ActionHost::class, ['definition' => 'subjects', 'placement' => 'record', 'record' => $id])->call('run', 'delete');
    $actions->call('submit')->assertDispatched('gcego-action-completed');

    expect(Subject::query()->find($id))->toBeNull()
        ->and(infolistHost($id, 'archive')->html())->toContain('Algebra');

    infolistHost($id)->assertStatus(404);
});

it('turns a host into the unavailable state when its record disappears', function (): void {
    $host = infolistHost($this->world['subject']->id);
    $this->world['subject']->delete();

    expect($host->dispatch('gcego-action-completed', result: ['status' => 'success'])->html())->toContain('This record is not available.');
});

it('edits through the record Edit action with the gcego/forms form', function (): void {
    $actions = Livewire::test(ActionHost::class, ['definition' => 'subjects', 'placement' => 'record', 'record' => $this->world['subject']->id])->call('run', 'edit');

    expect($actions->html())->toContain('wire:model="input.name"');

    $actions->set('input.name', 'Renamed')->call('submit')->assertDispatched('gcego-action-completed');

    expect(infolistHost($this->world['subject']->id)->html())->toContain('Renamed');
});

it('registers the Livewire alias, renderers, slots and the package', function (): void {
    expect(Livewire::test(InfolistsServiceProvider::LivewireAlias, ['definition' => 'minimal', 'record' => $this->world['subject']->id])->instance())->toBeInstanceOf(InfolistHost::class)
        ->and(app(RendererRegistry::class)->has(new Text('a')))->toBeTrue()
        ->and(app(RendererRegistry::class)->has(new Section('S', new Text('a'))))->toBeTrue()
        ->and(app(Gcego\Core\Gcego::class)->hasPackage('gcego/infolists'))->toBeTrue()
        ->and(config('gcego-infolists.layouts.default_columns'))->toBe(2);
});
