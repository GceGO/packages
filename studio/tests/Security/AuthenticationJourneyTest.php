<?php

declare(strict_types=1);

use Gcego\Studio\Tests\Fixtures\Models\User;
use Gcego\Studio\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    TestCase::$sentinel = true;
    $this->reboot([]);
    $this->world = world();
    User::query()->update(['password' => Hash::make('correct-horse')]);
});

it('adds gcego/sentinel\'s account and two-factor checks to every panel route', function (): void {
    $route = app('router')->getRoutes()->getByName('gcego.admin.academics.subjects.index');

    expect($route?->gatherMiddleware())->toContain('web', 'sentinel.active', 'sentinel.two-factor', 'auth', 'gcego.panel:admin');
});

it('signs in through Sentinel, enters the permitted panel, and is refused where not permitted', function (): void {
    $this->get('/admin/subjects')->assertRedirect('/login');
    $this->get('/login')->assertOk();

    $this->withSession(tenantSession())->get('/login');
    $before = session()->getId();
    $this->post('/login', ['email' => 'editor@example.test', 'password' => 'correct-horse'])->assertRedirect();

    $this->assertAuthenticatedAs($this->world['editor']);
    expect(session()->getId())->not->toBe($before);   // session fixation: the id was regenerated

    $this->withSession(tenantSession())->get('/admin/subjects')->assertOk()->assertSee('Chemistry')->assertDontSee('Biology');
    $this->withSession(tenantSession())->get('/admin/subjects/'.$this->world['biology']->id)->assertNotFound();

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
    $this->get('/admin/subjects')->assertRedirect('/login');

    $this->post('/login', ['email' => 'reader@example.test', 'password' => 'correct-horse']);
    $this->assertAuthenticatedAs($this->world['reader']);
    $this->withSession(tenantSession())->get('/admin/subjects')->assertForbidden();
});

it('refuses wrong passwords and suspended accounts at sign-in', function (): void {
    $this->post('/login', ['email' => 'editor@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->world['editor']->forceFill(['status' => 'suspended'])->save();
    $this->post('/login', ['email' => 'editor@example.test', 'password' => 'correct-horse']);
    $this->assertGuest();
});

it('signs an account suspended mid-session out of the panel on its next request', function (): void {
    $this->post('/login', ['email' => 'editor@example.test', 'password' => 'correct-horse']);
    $this->withSession(tenantSession())->get('/admin/subjects')->assertOk();

    $this->world['editor']->forceFill(['status' => 'suspended'])->save();
    app('auth')->forgetGuards();   // a new request loads the user from the session again, as in production
    $response = $this->withSession(tenantSession())->get('/admin/subjects');

    expect($response->status())->toBeIn([302, 303, 403])->and($response->getContent())->not->toContain('Chemistry');
    $this->assertGuest();
});
