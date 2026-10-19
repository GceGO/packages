# Livewire

## Component tests

`GcegoTest::livewire($class, $parameters)` mounts the component with Livewire's own testing API, as the user and tenant you choose. `test()` returns Livewire's `Testable`, so `assertSee`, `set`, `call`, `assertHasErrors`, `assertDispatched`, `assertRedirect`, `assertStatus` and the rest all work:

<!-- example:livewire-guide -->
```php
use Gcego\Forms\Livewire\FormHost;
use Gcego\Infolists\Livewire\InfolistHost;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Testing\GcegoTest;

GcegoTest::livewire(FormHost::class, ['definition' => 'subjects'])->actingAs($admin, 1)->test()
    ->set('data.name', '')
    ->call('submit')
    ->assertHasErrors(['data.name']);

GcegoTest::livewire(InfolistHost::class, ['definition' => 'subjects', 'record' => $southern->id])
    ->actingAs($admin, 1)
    ->assertMountRefused(404);

GcegoTest::livewire(TableHost::class, ['definition' => 'subjects'])
    ->actingAs($admin, 1)
    ->assertLocked('definition', 'blueprint:other');
```

- `assertMountRefused($status)` uses Livewire's `assertStatus()` on the mount: 403 for a denied user, 404 for a record outside the tenant.
- `assertLocked($property, $value)` expects `CannotUpdateLockedPropertyException`.

## Real requests

`Livewire::test()` does not run your middleware. To prove the server refuses forged requests, render a real page, take the component's snapshot, and post updates through the HTTP kernel with `GcegoTest::livewireRequests()`. Persistent middleware runs (tenant resolution, panel access), and so do checksums, locked properties and hydration:

```php
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Livewire\LivewireRequests;

$session = [app(Gcego\Security\SecurityConfig::class)->tenantSessionKey => '1'];
$html = $this->actingAs($admin)->withSession($session)->get('/admin/academics/courses')->getContent();
$snapshot = LivewireRequests::snapshot($html, 'gcego-table');
$requests = GcegoTest::livewireRequests();
$adapter = GcegoTest::livewire(TableHost::class);

$requests->call($snapshot, '$refresh')->assertOk()->assertDontSee('Southern');

// A snapshot edited in the browser: Livewire's checksum rejects it.
$adapter->assertRequestRefused($requests->update(LivewireRequests::forge($snapshot, ['definition' => 'blueprint:other']), session: $session));

// Another tenant in the session: the persistent tenant middleware rejects it.
$adapter->assertRequestRefused($requests->update($snapshot, session: [app(Gcego\Security\SecurityConfig::class)->tenantSessionKey => '2']));

// A user whose access was revoked since the page loaded: panel access is re-checked.
$this->actingAs($editor);
$adapter->assertRequestRefused($requests->update($snapshot, session: $session));
```

| Method | Does |
| --- | --- |
| `LivewireRequests::snapshot($html, $name)` / `snapshots()` | the decoded `wire:snapshot` of the component(s) named `$name` |
| `LivewireRequests::forge($snapshot, $changes)` | changes the snapshot data without re-signing it |
| `update($snapshot, $calls, $updates, $session)` | one update request; returns a `TestResponse` |
| `call($snapshot, $method, ...$params)` | one method call |

`update()` flushes Livewire's state and starts a new request first, as a browser round trip would.

For PHPUnit test cases, `InteractsWithGcegoLivewire` adds `gcegoLivewire()`, `gcegoSnapshot()` and `gcegoLivewireUpdate()`.
