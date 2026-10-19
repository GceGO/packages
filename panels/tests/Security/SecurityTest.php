<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Contracts\PanelAccessPolicy;
use Gcego\Panels\Exceptions\PanelAccessDeniedException;
use Gcego\Panels\Panel;
use Gcego\Panels\Tests\Fixtures\Models\User;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\TenantMember;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class ExplodingAccess implements PanelAccessPolicy
{
    public function allows(Authenticatable $user, PanelContext $context): bool
    {
        throw new RuntimeException('Directory unavailable.');
    }
}

it('checks the panel again on every Livewire update, for the page the component was mounted on', function (): void {
    $world = world();
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/subjects')->getContent(), 'gcego-table');

    $this->actingAs($world['admin']->fresh());
    livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertOk();

    $this->actingAs($world['editor']->fresh());
    $replayed = livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertForbidden();

    expect($replayed->exception)->toBeInstanceOf(PanelAccessDeniedException::class);
});

it('stops Livewire updates as soon as panel access is revoked', function (): void {
    $world = world();
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/subjects')->getContent(), 'gcego-table');
    Gate::define('access-admin', static fn (): bool => false);

    $this->actingAs($world['admin']->fresh());
    livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertForbidden();
});

it('keeps the panel context out of Livewire snapshots and the page', function (): void {
    $world = world();
    $html = (string) asMember($world['admin'])->get('/admin/subjects')->getContent();

    foreach ([snapshotOf($html, 'gcego-table'), snapshotOf($html, 'gcego-page')] as $snapshot) {
        expect($snapshot)->not->toBe('')
            ->not->toContain('access-admin')
            ->not->toContain('PanelContext')
            ->not->toContain('gcego.panel');
    }

    expect($html)->not->toContain('access-admin')->not->toContain(ExplodingAccess::class);
});

it('ignores panel names supplied by the browser', function (): void {
    $world = world();

    asMember($world['editor'])->get('/staff?panel=admin')->assertOk();
    asMember($world['editor'])->withHeaders(['X-Gcego-Panel' => 'admin'])->get('/admin/subjects?panel=staff')->assertForbidden();

    expect(app(PanelContextResolver::class)->current())->toBeNull();
});

it('never lets access to one panel imply another', function (): void {
    $world = world();

    asMember($world['editor'])->get('/staff')->assertOk();
    asMember($world['editor'])->get('/admin/overview')->assertForbidden();
    asMember($world['reader'])->get('/school/1')->assertOk();
    asMember($world['reader'])->get('/staff')->assertForbidden();
});

it('denies when the access policy fails', function (): void {
    $world = world();
    routePanel(new Panel(new Property(Panel::Name, 'fragile'), new Property(Panel::AccessPolicy, ExplodingAccess::class)));

    asMember($world['admin'])->get('/fragile')->assertForbidden();
});

it('requires a tenant for tenant panels', function (): void {
    $world = world();
    routePanel(new Panel(new Property(Panel::Name, 'campus'), new Property(Panel::Tenant, 'required'), new Property(Panel::Domain, '{institution}.campus.test'), new TenantMember));

    asMember($world['reader'])->get('http://1.campus.test/campus')->assertOk();
    asMember($world['reader'])->get('http://2.campus.test/campus')->assertNotFound();
});

it('uses the panel guard and never another guard\'s session', function (): void {
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    $world = world();
    routePanel(new Panel(new Property(Panel::Name, 'backstage'), new Property(Panel::Guard, 'staff'), new Authenticated));

    asMember($world['admin'])->get('/backstage')->assertRedirect('/login');
    $this->actingAs($world['admin']->fresh(), 'staff')->get('/backstage')->assertOk();
});

it('escapes branding text', function (): void {
    $world = world();
    $html = (string) asMember($world['admin'])->get('/admin/overview')->getContent();

    expect($html)->toContain('GCEGO &lt;Admin&gt;')->not->toContain('GCEGO <Admin>');
});

it('signs out with a POST form carrying the CSRF token', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toMatch('/<form method="POST" action="\/logout"> <input type="hidden" name="_token" value="[^"]+"/');
});

it('does not trust a user model as a panel user once it is gone', function (): void {
    $world = world();
    User::query()->whereKey($world['editor']->id)->delete();

    $this->get('/staff')->assertRedirect('/login');
});
