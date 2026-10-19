<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Livewire\ActionHost;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('authorizes already-loaded records for display without another lookup', function (): void {
    actAs($this->world['admin']);
    $manager = app(ActionManager::class);
    $algebra = $this->world['subjects']['algebra'];

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($manager->allowsLoaded($manager->resolve('subjects', 'delete'), $algebra))->toBeTrue()
        ->and($manager->allowsLoaded($manager->resolve('subjects', 'create'), $algebra))->toBeFalse()
        ->and($queries)->toBe(0);

    actAs($this->world['editor']);

    expect(app(ActionManager::class)->allowsLoaded(app(ActionManager::class)->resolve('subjects', 'delete'), $algebra))->toBeFalse();
});

it('runs requests sent on its own channel only, with untrusted record keys', function (): void {
    actAs($this->world['admin']);
    $algebra = $this->world['subjects']['algebra'];

    $host = Livewire::test(ActionHost::class, ['definition' => 'subjects', 'channel' => 'grid', 'showButtons' => false]);
    $host->dispatch('gcego-actions-run', channel: 'other', action: 'delete', record: (string) $algebra->id);

    expect($host->get('dialog'))->toBeNull();

    $host->dispatch('gcego-actions-run', channel: 'grid', action: 'delete', record: (string) $algebra->id);

    expect($host->get('dialog.mode'))->toBe('confirm')
        ->and($host->get('record'))->toBe((string) $algebra->id)
        ->and($host->html())->not->toContain('data-gcego-action=');

    $unchanneled = Livewire::test(ActionHost::class, ['definition' => 'subjects'])->dispatch('gcego-actions-run', channel: 'grid', action: 'delete', record: (string) $algebra->id);

    expect($unchanneled->get('dialog'))->toBeNull();
});
