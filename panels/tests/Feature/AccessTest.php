<?php

declare(strict_types=1);

use Gcego\Panels\Exceptions\PanelAccessDeniedException;

it('sends guests to the sign-in page', function (string $uri): void {
    world();

    $this->get($uri)->assertRedirect('/login');
})->with(['/admin', '/admin/subjects', '/school/1/subjects', '/staff', '/vault']);

it('admits users the panel requirements allow, and nobody else', function (): void {
    $world = world();

    asMember($world['admin'])->get('/admin/subjects')->assertOk()->assertSee('Chemistry');
    $denied = asMember($world['editor'])->get('/admin/subjects')->assertForbidden();

    expect($denied->exception)->toBeInstanceOf(PanelAccessDeniedException::class);
});

it('admits users an access policy allows', function (): void {
    $world = world();

    asMember($world['editor'])->get('/staff')->assertOk()->assertSee('Welcome to Staff Room');
    asMember($world['reader'])->get('/staff')->assertForbidden();
});

it('denies everybody when a panel declares no access rules', function (): void {
    $world = world();

    asMember($world['admin'])->get('/vault')->assertForbidden();
});

it('sends the panel home to its dashboard', function (): void {
    $world = world();

    asMember($world['admin'])->get('/admin')->assertRedirect('/admin/overview');
    asMember($world['admin'])->get('/admin/overview')->assertOk()->assertSee('Everything at a glance.');
});

it('keeps page authorization inside a panel', function (): void {
    $world = world();

    asMember($world['editor'])->get('/staff/reports')->assertForbidden();
    asMember($world['admin'])->get('/staff/reports')->assertOk()->assertSee('Monthly figures.');
});

it('keeps record authorization inside a panel', function (): void {
    $world = world();
    $locked = $world['subjects'][2];

    asMember($world['admin'])->get('/admin/subjects/'.$world['subjects'][0]->id)->assertOk()->assertSee('Chemistry');
    $denied = asMember($world['admin'])->get('/admin/subjects/'.$locked->id)->assertForbidden();

    expect($denied->exception)->not->toBeInstanceOf(PanelAccessDeniedException::class);
});

it('serves only the pages a panel includes', function (): void {
    $world = world();

    asMember($world['editor'])->get('/staff/subjects')->assertNotFound();
    asMember($world['admin'])->get('/admin/help')->assertNotFound();
    asMember($world['admin'])->get('/help')->assertNotFound();
});

it('admits tenant members to their own tenant only', function (): void {
    $world = world();

    asMember($world['reader'])->get('/school/1/subjects')->assertOk()->assertSee('Chemistry')->assertDontSee('Biology');
    asMember($world['south'], 2)->get('/school/2/subjects')->assertOk()->assertSee('Biology')->assertDontSee('Chemistry');
    asMember($world['south'], 2)->get('/school/1/subjects')->assertNotFound();
    asMember($world['reader'])->get('/school/3/subjects')->assertNotFound();
});

it('scopes records to the tenant the URL names', function (): void {
    $world = world();
    $biology = $world['subjects'][3];

    asMember($world['reader'])->get('/school/1/subjects/'.$biology->id)->assertNotFound();
    asMember($world['south'], 2)->get('/school/2/subjects/'.$biology->id)->assertOk()->assertSee('Biology');
});
