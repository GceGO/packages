<?php

declare(strict_types=1);

use Gcego\Navigation\Tests\Fixtures\Livewire\Shell;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('keeps the active page, expanded groups and morph-safe markup across real Livewire updates', function (): void {
    $page = test()->get('/admin/subjects/1/live')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $page, $snapshot);
    $before = squish($page);

    $response = test()->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => html_entity_decode($snapshot[1] ?? '', ENT_QUOTES), 'updates' => (object) [], 'calls' => [['path' => '', 'method' => 'increment', 'params' => []]]]],
    ])->assertOk();
    $after = squish((string) $response->json('components.0.effects.html'));

    expect($before)->toMatch('/<a href="\/admin\/subjects"[^>]*aria-current="page"/')
        ->and($after)->toContain('Clicked 1')
        ->and($after)->toMatch('/<a href="\/admin\/subjects"[^>]*aria-current="page"/')
        ->and(substr_count($after, 'aria-current="page"'))->toBe(1)
        ->and($after)->toMatch('/academic-management-toggle" aria-expanded="true"/')
        ->and(substr_count($after, 'wire:ignore.self'))->toBeGreaterThan(0)
        ->and(substr_count($after, 'wire:ignore.self'))->toBe(substr_count($before, 'wire:ignore.self'));
});

it('keeps navigation state out of the Livewire snapshot', function (): void {
    onPage('/admin/subjects/1');
    $snapshot = (string) json_encode(Livewire::test(Shell::class)->snapshot);

    expect($snapshot)->not->toContain('Subjects')
        ->and($snapshot)->not->toContain('pending')
        ->and($snapshot)->not->toContain('compact');
});

it('uses standard links, not Livewire components, for items', function (): void {
    onPage('/admin');
    $html = squish(Livewire::test(Shell::class)->html());

    expect(substr_count($html, 'wire:id'))->toBe(1)
        ->and(substr_count($html, '<a href="/admin'))->toBeGreaterThan(5);
});
