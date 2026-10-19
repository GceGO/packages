<?php

declare(strict_types=1);

use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Panels\Exceptions\PanelAccessDeniedException;
use Gcego\Panels\Tests\Fixtures\Extensions\BannerExtension;

beforeEach(function (): void {
    $this->reboot(['gcego-panels.extensions' => [BannerExtension::class]]);
});

it('adds extension routes inside each panel, behind the panel checks', function (): void {
    $world = world();

    expect(route('gcego.admin.banner', [], false))->toBe('/admin/banner')
        ->and(route('gcego.school.banner', ['institution' => 1], false))->toBe('/school/1/banner')
        ->and(app('router')->getRoutes()->getByName('gcego.admin.banner')?->gatherMiddleware())->toBe(['web', 'auth', 'gcego.tenant:optional', 'gcego.panel:admin']);

    expect(html(asMember($world['admin'])->get('/admin/banner')->assertOk()))->toContain('<p>Banner of GCEGO Administration</p>')->toContain('data-gcego-panel="admin"');

    $denied = asMember($world['editor'])->get('/admin/banner')->assertForbidden();
    expect($denied->exception)->toBeInstanceOf(PanelAccessDeniedException::class);
});

it('renders extension markup in the top bar and the body of every layout', function (string $user, string $uri): void {
    $world = world();
    $html = html(asMember($world[$user])->get($uri)->assertOk());

    expect($html)->toContain('<span data-banner-topbar>')->toContain('<div data-banner-body></div>');
})->with([['admin', '/admin/overview'], ['reader', '/school/1/subjects'], ['editor', '/staff']]);

it('refuses extensions that do not implement the contract', function (): void {
    expect(fn () => panels()->extend(stdClass::class))->toThrow(InvalidPanelException::class, 'must implement');
});
