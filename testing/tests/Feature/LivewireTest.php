<?php

declare(strict_types=1);

use Gcego\Forms\Livewire\FormHost;
use Gcego\Infolists\Livewire\InfolistHost;
use Gcego\Security\SecurityConfig;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Livewire\LivewireRequests;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

it('mounts a component with Livewire\'s testing API as the acting user', function (): void {
    $world = world();

    GcegoTest::livewire(TableHost::class, ['definition' => 'subjects'])
        ->actingAs($world['admin'], 1)
        ->assertSee('Algebra')
        ->assertDontSee('Southern')
        ->set('search', 'dra')
        ->assertSee('Draft')
        ->assertDontSee('Algebra');
});

it('drives a form host through validation and submission', function (): void {
    $world = world();

    GcegoTest::livewire(FormHost::class, ['definition' => 'subjects'])->actingAs($world['admin'], 1)->test()
        ->set('data.name', '')
        ->call('submit')
        ->assertHasErrors(['data.name'])
        ->set('data.name', 'Geometry')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('gcego-action-completed');

    expect(Subject::query()->where('name', 'Geometry')->value('institution_id'))->toBe(1);
});

it('refuses to mount for records outside the tenant or forbidden by policy', function (): void {
    $world = world();

    GcegoTest::livewire(InfolistHost::class, ['definition' => 'subjects', 'record' => $world['southern']->id])->actingAs($world['admin'], 1)->assertMountRefused(404);
    GcegoTest::livewire(InfolistHost::class, ['definition' => 'subjects', 'record' => $world['draft']->id])->actingAs($world['student'], 1)->assertMountRefused(403);
});

it('refuses client writes to locked properties', function (): void {
    $world = world();

    GcegoTest::livewire(TableHost::class, ['definition' => 'subjects'])->actingAs($world['admin'], 1)->assertLocked('definition', 'blueprint:other');
    GcegoTest::livewire(InfolistHost::class, ['definition' => 'subjects', 'record' => $world['algebra']->id])->actingAs($world['admin'], 1)->assertLocked('record', $world['southern']->id);
});

it('sends real update requests through the HTTP kernel', function (): void {
    $world = world();
    $session = [app(SecurityConfig::class)->tenantSessionKey => '1'];
    $html = (string) $this->actingAs($world['admin'])->withSession($session)->get('/admin/academics/courses')->assertOk()->getContent();
    $snapshot = LivewireRequests::snapshot($html, 'gcego-table');

    expect($snapshot)->not->toBe('');

    $requests = GcegoTest::livewireRequests();
    $requests->call($snapshot, '$refresh')->assertOk()->assertSee('Algebra')->assertDontSee('Southern');

    // The module's table declares no searchable column, so the server resets a forged search.
    $requests->update($snapshot, updates: ['search' => 'alg'], session: $session)->assertOk()->assertSee('Some table settings were not valid and were reset.');
});

it('rejects a forged snapshot, a forged tenant and a revoked user', function (): void {
    $world = world();
    $session = [app(SecurityConfig::class)->tenantSessionKey => '1'];
    $html = (string) $this->actingAs($world['admin'])->withSession($session)->get('/admin/academics/courses')->getContent();
    $snapshot = LivewireRequests::snapshot($html, 'gcego-table');
    $requests = GcegoTest::livewireRequests();
    $adapter = GcegoTest::livewire(TableHost::class);

    $adapter->assertRequestRefused($requests->update(LivewireRequests::forge($snapshot, ['definition' => 'blueprint:other']), session: $session));
    $adapter->assertRequestRefused($requests->update($snapshot, session: [app(SecurityConfig::class)->tenantSessionKey => '2']));

    $this->actingAs($world['editor']);
    $adapter->assertRequestRefused($requests->update($snapshot, session: $session));
});

it('offers the same helpers to PHPUnit test cases', function (): void {
    $world = world();
    $this->actingAsGcego($world['admin'], 1);

    $this->gcegoLivewire(TableHost::class, ['definition' => 'subjects'])->assertSee('Algebra');
    expect($this->gcegoSnapshot('<div wire:snapshot="{&quot;a&quot;:1}" wire:name="x"></div>', 'x'))->toBe('{"a":1}');
});
