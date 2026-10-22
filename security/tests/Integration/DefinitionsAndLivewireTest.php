<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Registry\Registry;
use Gcego\Rendering\Contracts\RenderAuthorizer;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\Integration\SecurityRenderAuthorizer;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\Security;
use Gcego\Security\Tests\Fixtures\Livewire\SubjectEditor;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    $registry = app(Registry::class);
    $registry->register(new Blueprint('subjects', Permission::inTenant('subjects.view'), new Source(Subject::class)));
    $registry->register(new Blueprint('members', new Security(new Property(Security::Permission, 'members.view'), new Property(Security::Tenant, true))));
    $registry->register(new Blueprint('unprotected'));
    $registry->register(new Blueprint('welcome', new PublicAccess));
});

it('replaces the runtime default authorizer', function (): void {
    expect(app(RenderAuthorizer::class))->toBeInstanceOf(SecurityRenderAuthorizer::class);
});

it('denies definitions without requirements and allows explicitly public ones', function (): void {
    expect(security()->definition('blueprint:unprotected')->reason?->value)->toBe('no_requirement')
        ->and(security()->definition('blueprint:welcome')->allowed)->toBeTrue()
        ->and(security()->definition('blueprint:missing')->status)->toBe(404);
});

it('collects requirements from the root to the target component', function (): void {
    Gate::define('subjects.delete', fn (User $user): bool => $user->name === 'Admin');
    $blueprint = new Blueprint('catalogue', new Permission('catalogue.access'), new Source(Subject::class, new Permission('subjects.delete')));
    Gate::define('catalogue.access', fn (User $user): bool => true);
    $path = 'catalogue.#0';

    $this->actingAs($this->world['teacher']);
    expect(security()->definition($blueprint)->allowed)->toBeTrue()
        ->and(security()->definition($blueprint, $path)->reason?->value)->toBe('missing_permission')
        ->and(security()->definition($blueprint, 'catalogue.nope')->status)->toBe(404);

    $this->actingAs($this->world['admin']);
    newRequest();
    expect(security()->definition($blueprint, $path)->allowed)->toBeTrue();
});

it('denies a bulk operation when any record is denied', function (): void {
    $blueprint = new Blueprint('bulk', new Ability('view'));
    $this->actingAs($this->world['teacher']);
    $subjects = $this->world['subjects'];

    expect(security()->bulk($blueprint, null, [$subjects['algebra'], $subjects['south']])->allowed)->toBeTrue()
        ->and(security()->bulk($blueprint, null, [$subjects['algebra'], $subjects['draft']])->metadata)->toBe(['checked' => 2])
        ->and(security()->bulk($blueprint, null, [])->allowed)->toBeFalse();
});

it('authorizes runtime renders of registered definitions', function (): void {
    $this->actingAs($this->world['teacher']);

    expect(fn () => Rendering::renderAuthorized('blueprint:subjects'))->toThrow(AccessDeniedException::class);

    security()->enterTenant((string) $this->world['north']->id);

    expect(Rendering::renderAuthorized('blueprint:subjects')->toHtml())->toContain('Subjects');
});

it('authorizes the Livewire host initially and again on every interaction', function (): void {
    $this->actingAs($this->world['teacher']);
    security()->enterTenant((string) $this->world['north']->id);

    $component = Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->assertOk();

    // The membership ends between two interactions.
    Membership::query()->where('user_id', $this->world['teacher']->id)->update(['status' => 'ended']);
    newRequest();
    try {
        security()->enterTenant((string) $this->world['north']->id);
    } catch (TenantAccessException) {
        // The persistent tenant middleware rejects the next request; without a tenant the host is denied too.
    }

    $component->call('$refresh')->assertForbidden();
});

it('re-checks the tenant permission when the tenant changes between interactions', function (): void {
    $this->actingAs($this->world['admin']);
    security()->enterTenant((string) $this->world['north']->id);
    $component = Livewire::test(BlueprintHost::class, ['blueprint' => 'members'])->assertOk();

    newRequest();
    security()->enterTenant((string) $this->world['south']->id);

    $component->call('$refresh')->assertForbidden();
});

it('cannot be pointed at another definition through public state', function (): void {
    $this->actingAs($this->world['teacher']);
    security()->enterTenant((string) $this->world['north']->id);

    expect(fn () => Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->set('blueprint', 'blueprint:members'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('re-resolves and re-authorizes records inside Livewire actions', function (): void {
    Gate::define('subjects.publish', fn (User $user): bool => false);
    $editor = User::query()->create(['name' => 'Editor']);
    Membership::query()->create(['user_id' => $editor->id, 'institution_id' => $this->world['north']->id, 'role' => 'teacher']);
    $this->actingAs($editor);
    security()->enterTenant((string) $this->world['north']->id);
    $algebra = $this->world['subjects']['algebra'];

    $component = Livewire::test(SubjectEditor::class, ['subject' => $algebra->id])->set('name', 'Linear Algebra')->call('save')->assertOk();
    expect($algebra->refresh()->name)->toBe('Linear Algebra');

    // A field the user may not write, set through public state.
    $component->set('isPublished', true)->call('save')->assertForbidden();

    // A forged record id cannot be set (locked), and the record of another tenant is not mountable.
    expect(fn () => Livewire::test(SubjectEditor::class, ['subject' => $algebra->id])->set('subject', $this->world['subjects']['south']->id))->toThrow(CannotUpdateLockedPropertyException::class);
    Livewire::test(SubjectEditor::class, ['subject' => $this->world['subjects']['south']->id])->assertNotFound();

    // A private method is not a callable action, whatever the client sends.
    expect(fn () => Livewire::test(SubjectEditor::class, ['subject' => $algebra->id])->call('record', 'delete'))->toThrow(MethodNotFoundException::class);
});

it('stops a Livewire action whose permission was revoked after mounting', function (): void {
    $editor = User::query()->create(['name' => 'Editor']);
    Membership::query()->create(['user_id' => $editor->id, 'institution_id' => $this->world['north']->id, 'role' => 'teacher']);
    $this->actingAs($editor);
    security()->enterTenant((string) $this->world['north']->id);
    $component = Livewire::test(SubjectEditor::class, ['subject' => $this->world['subjects']['draft']->id]);

    $editor->update(['name' => 'Former editor']);
    newRequest();
    security()->enterTenant((string) $this->world['north']->id);

    $component->set('name', 'Changed')->call('save')->assertForbidden();
    expect($this->world['subjects']['draft']->refresh()->name)->toBe('Draft Physics');
});
