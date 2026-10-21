<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Navigation\Components\ExternalLink;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\Tests\Fixtures\Providers\PendingRequestsBadge;
use Gcego\Registry\Registry;
use Gcego\Security\SecurityManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('renders only registered identities and refuses forged ones', function (string $identity): void {
    expect(statusOf(fn () => navigation()->render($identity)))->toBe(404);
})->with(['nope', 'navigation:nope', 'Gcego\\Navigation\\Navigation', 'blueprint:admin', 'navigation:../admin', 'navigation:ADMIN', 'navigation:admin;drop']);

it('refuses unknown displays', function (): void {
    expect(fn () => navigation()->render('admin', 'sidebar" onload="x'))->toThrow(InvalidNavigationException::class, 'use one of sidebar, topbar, mobile, breadcrumbs')
        ->and(squish(Blade::render('<x-gcego-navigation::navigation name="admin" display="topbar" />')))->toContain('flex-wrap');
});

it('never builds a navigation with unsafe URLs', function (Navigation $navigation): void {
    expect(fn () => navigation()->tree($navigation))->toThrow(InvalidNavigationException::class);
})->with([
    'javascript item' => [fn () => new Navigation('a', new Item('Evil', 'javascript:alert(1)'))],
    'javascript external' => [fn () => new Navigation('b', new ExternalLink('Evil', 'javascript:alert(1)'))],
    'vbscript' => [fn () => new Navigation('c', new Item(new Property(Item::Label, 'Evil'), new Property(Item::Url, 'vbscript:msgbox(1)')))],
    'quotes' => [fn () => new Navigation('d', new Item('Evil', '/admin" onclick="x'))],
]);

it('takes badge providers from definitions only', function (): void {
    onPage('/admin?badge='.urlencode(PendingRequestsBadge::class).'&provider=App\\Evil');
    PendingRequestsBadge::$calls = 0;
    $html = navigation()->render('admin')->toHtml();

    expect(PendingRequestsBadge::$calls)->toBe(1)
        ->and($html)->not->toContain('App\\Evil')
        ->and($html)->not->toContain('PendingRequestsBadge');
});

it('never reuses one user\'s navigation for another in the same process', function (): void {
    Gate::define('institution.members.manage', fn ($user): bool => $user->name === 'Admin');
    app(SecurityManager::class)->enterTenant('1');
    onPage('/admin');

    $security = app(SecurityManager::class);
    $admin = $security->actingAs($this->world['admin'], '1', fn (): string => navigation()->render('admin')->toHtml());
    $teacher = $security->actingAs($this->world['teacher'], '1', fn (): string => navigation()->render('admin')->toHtml());
    $adminAgain = $security->actingAs($this->world['admin'], '1', fn (): string => navigation()->render('admin')->toHtml());

    expect($admin)->toContain('Institutions')
        ->and($teacher)->not->toContain('Institutions')
        ->and($adminAgain)->toBe($admin);
});

it('never shows another tenant\'s badge counts or routes', function (): void {
    onPage('/admin');
    $north = navigation()->render('admin')->toHtml();
    actAs($this->world['south'], 2);
    onPage('/admin');
    $south = navigation()->render('admin')->toHtml();

    expect($north)->toContain('<span aria-hidden="true">3</span>')
        ->and($north)->toContain('/admin/institutions/1/members')
        ->and($south)->toContain('<span aria-hidden="true">1</span>')
        ->and($south)->toContain('/admin/institutions/2/members')
        ->and($south)->not->toContain('/admin/institutions/1/');
});

it('keeps hiding links separate from access control', function (): void {
    app(Registry::class)->register(new Navigation('hidden', new Item(new Property(Item::Label, 'Vault'), new Property(Item::Url, '/admin/topics'), new Property(Item::Permission, 'vault.view'))));
    onPage('/admin');

    expect(navigation()->render('hidden')->toHtml())->toBe('')
        ->and(test()->get('/admin/topics')->status())->toBe(200);
});
