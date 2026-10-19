<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Property;
use Gcego\Navigation\Components\ExternalLink;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\NavigationManager;
use Gcego\Registry\Registry;
use Gcego\Runtime\Exceptions\RenderException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

function page(string $uri): string
{
    return squish(test()->get($uri)->assertOk()->getContent());
}

it('renders the sidebar as a labelled nav landmark with lists', function (): void {
    $html = page('/admin/subjects/1');

    expect($html)->toMatch('/^<nav id="[^"]+" aria-label="Administration" class="group\/nav flex min-w-0 flex-col gap-1 text-sm"/')
        ->and($html)->toContain('<ul id="gcego-navigation-admin-admin-dfe2d6fe-list" role="list"')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(2)
        ->and($html)->toMatch('/<a href="\/admin\/subjects" class="[^"]*bg-gcego-primary\/10 text-gcego-primary" aria-current="page"/')
        ->and($html)->toMatch('/<a href="\/admin\/students" class="[^"]*text-gcego-text-muted hover:bg-gcego-surface-muted hover:text-gcego-text"\s*x-on:mouseenter="hoverTip\(\'[^\']+-students-tip\'\)"/')
        ->and($html)->toMatch('/<li class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gcego-text-muted [^"]*group-data-\[compact\]\/nav:h-px[^"]*" x-show="! searching">Planning<\/li>/')
        ->and($html)->toContain('<li aria-hidden="true" class="my-2 border-t border-gcego-border group-data-[compact]/nav:mx-auto group-data-[compact]/nav:w-5" x-show="! searching"></li>')
        ->and($html)->not->toContain('Secrets')
        ->and($html)->not->toContain('Missing')
        ->and($html)->not->toContain('Institutions');
});

it('renders collapsible groups as disclosure buttons controlling their list', function (): void {
    $html = page('/admin/subjects/1');

    preg_match('/<li x-data="\{ open: false \}" x-show="foundAny\([^"]*\)"> <button type="button" id="([^"]+-administration-toggle)" aria-expanded="false" aria-controls="([^"]+-administration-items)" x-bind:aria-expanded="open.toString\(\)" x-on:click="open = ! open" x-bind:tabindex="compact \? -1 : null" wire:ignore.self/', $html, $administration);

    expect($administration)->toHaveCount(3)
        ->and($html)->toContain('<ul id="'.$administration[2].'" role="list" aria-labelledby="'.$administration[1].'" hidden x-bind:hidden="! open &amp;&amp; ! compact &amp;&amp; ! searching" wire:ignore.self')
        ->and($html)->toMatch('/<li x-data="\{ open: true \}" x-show="foundAny\([^"]*\)"> <button type="button" id="[^"]+-academic-management-toggle" aria-expanded="true"/')
        ->and($html)->toMatch('/<ul id="[^"]+-academic-management-items" role="list" aria-labelledby="[^"]+" x-bind:hidden="! open &amp;&amp; ! compact &amp;&amp; ! searching"/');
});

it('keeps the compact sidebar usable: groups stay open as dividers, chevrons hide, links name themselves in a tooltip and the toggle turns', function (): void {
    $html = page('/admin/subjects/1');

    expect($html)->toMatch('/<svg [^>]*class="[^"]*group-data-\[compact\]\/nav:hidden[^"]*"[^>]*data-gcego-icon="heroicon-mini:chevron-down"/')
        ->and($html)->toMatch('/x-on:click="open = ! open" x-bind:tabindex="compact \? -1 : null" wire:ignore.self class="[^"]*group-data-\[compact\]\/nav:h-px/')
        ->and($html)->toContain('<span class="inline-flex transition-transform duration-300 ease-in-out motion-reduce:transition-none group-data-[compact]/nav:rotate-180">')
        ->and($html)->toMatch('/<a href="\/admin\/subjects"[^>]* x-on:mouseenter="hoverTip\(\'([^\']+-tip)\'\)" x-on:mouseleave="leaveTip\(\)" x-on:focus="focusTip\(\'\1\'\)" x-on:blur="leaveTip\(\)"[^>]*>.*?<span aria-hidden="true" x-cloak x-show="compact && tip === \'\1\'" x-transition:enter="transition duration-150 ease-out[^"]*"[^>]*class="pointer-events-none absolute start-full[^"]*"> Subjects <\/span> <\/a>/')
        ->and($html)->toContain('x-bind:data-compact="compact" x-on:keydown.escape="tip = null"')
        ->and($html)->not->toContain('style=')
        ->and($html)->not->toContain('x-bind:class="compact');
});

it('applies a remembered compact state before the first paint, with the CSP nonce, and hands it to Alpine', function (): void {
    Vite::useCspNonce('n0nce');
    $html = page('/admin/subjects/1');

    expect($html)->toMatch('/<\/nav> <script nonce="n0nce">\(function \(\) \{ try \{ var stored = localStorage\.getItem\(\'gcego-navigation:navigation:admin:compact\'\);/')
        ->toContain("nav.setAttribute('data-compact', 'true')")
        ->toContain("el.removeAttribute('hidden')");
});

it('renders badges with their meaning for screen readers, links to the tenant, and external links safely', function (): void {
    $html = page('/admin');

    expect($html)->toContain('<span aria-hidden="true">3</span><span class="sr-only">3 pending requests</span>')
        ->and($html)->toContain('href="/admin/institutions/1/members"')
        ->and($html)->toMatch('/<a href="https:\/\/docs.example.com\/gcego"[^>]* target="_blank" rel="noopener noreferrer"\s*data-gcego-search\s*>/')
        ->and($html)->toContain('<span class="sr-only">(opens in a new tab)</span>');
});

it('floats the badge of a link with an icon over the icon, open or compact, and keeps it at the end otherwise', function (): void {
    app(Registry::class)->register(new Navigation('badged',
        new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Url, '/admin/students'), new Property(Item::Icon, 'navigation.home'), new Property(Item::Badge, 12)),
        new Item(new Property(Item::Name, 'fees'), new Property(Item::Label, 'Fees'), new Property(Item::Url, '/admin/fees'), new Property(Item::Badge, 'New'), new Property(Item::BadgeVariant, 'success')),
        new Item(new Property(Item::Name, 'alerts'), new Property(Item::Label, 'Alerts'), new Property(Item::Url, '/admin/alerts'), new Property(Item::Icon, 'navigation.home'), new Property(Item::Badge, 'Late'), new Property(Item::BadgeVariant, 'warning')),
    ));
    onPage('/admin');
    $html = squish(navigation()->render('badged')->toHtml());

    expect($html)->toContain('<span class="pointer-events-none absolute top-0.5 start-1 inline-flex h-4 min-w-4 items-center justify-center gap-1 rounded-full px-1 text-xs leading-none font-semibold tabular-nums ring-2 ring-gcego-surface bg-gcego-primary text-gcego-primary-foreground"> 12 </span>')
        ->and($html)->toContain('<span class="ms-auto inline-flex min-w-5 shrink-0 items-center justify-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums bg-gcego-success/15 text-gcego-success"> New </span>')
        ->and($html)->toContain('ring-2 ring-gcego-surface bg-gcego-warning text-gcego-background"> Late </span>')
        ->and($html)->not->toContain('group-data-[compact]/nav:absolute');
});

it('renders a sidebar search that filters links by their label and their groups\' labels', function (): void {
    $html = page('/admin/subjects/1');

    expect($html)->toContain('x-on:keydown.slash.window="shortcut($event)"')
        ->and($html)->toMatch('/<div role="search" class="[^"]*group-data-\[compact\]\/nav:hidden"> <svg [^>]*data-gcego-icon="heroicon:magnifying-glass"/')
        ->and($html)->toContain('<input type="text" role="searchbox" x-ref="search" x-model="query" x-on:keydown.escape.prevent.stop="clear()" x-on:keydown.enter.prevent="go()" aria-label="Search navigation"')
        ->and($html)->toContain('<kbd aria-hidden="true" x-show="! searching"')
        ->and($html)->toMatch('/<button type="button" x-on:click="search\(\)" aria-controls="[^"]+-list" class="hidden [^"]*group-data-\[compact\]\/nav:inline-flex">/')
        ->and($html)->toContain('<li x-show="found(\'administration requests\')">')
        ->and($html)->toMatch('/<p role="status" x-cloak x-show="searching && ! foundAny\(JSON\.parse\([^"]*administration requests[^"]*\)\)" class="[^"]*">No matching pages.<\/p>/');

    $mobile = page('/admin/students?display=mobile');

    expect($mobile)->not->toContain('found(')->not->toContain('role="searchbox"');
});

it('leaves the search out when gcego-navigation.sidebar.search is off', function (): void {
    config()->set('gcego-navigation.sidebar.search', false);
    app()->forgetInstance(NavigationConfig::class);
    app()->forgetInstance(NavigationManager::class);

    $html = page('/admin/subjects/1');

    expect($html)->not->toContain('role="searchbox"')
        ->and($html)->not->toContain('x-show="found(')
        ->and($html)->toContain('x-bind:hidden="! open &amp;&amp; ! compact"');
});

it('renders the top navigation with disclosure dropdowns (no ARIA menus)', function (): void {
    $html = page('/admin/reports/fees?display=topbar');

    expect($html)->toMatch('/^<nav id="[^"]+" aria-label="Administration" class="min-w-0"> <ul role="list" class="flex min-w-0 flex-wrap items-center gap-1">/')
        ->and($html)->toMatch('/<button type="button" x-ref="trigger" id="([^"]+-reports-toggle)" aria-expanded="false" aria-controls="([^"]+-reports-menu)" x-bind:aria-expanded="open.toString\(\)" x-on:click="toggle\(\)" x-on:keydown.arrow-down.prevent="show\(0\)" x-on:keydown.arrow-up.prevent="show\(-1\)" wire:ignore.self class="[^"]*bg-gcego-primary\/10/')
        ->and($html)->toMatch('/<div id="[^"]+-reports-menu" x-ref="panel" hidden x-bind:hidden="! open" wire:ignore.self x-on:keydown.arrow-down.prevent="move\(1\)"[^>]* class="absolute top-full z-30[^"]* end-0"/')
        ->and($html)->toContain('x-on:keydown.escape.prevent.stop="close(true)" x-on:click.outside="close(false)"')
        ->and($html)->toMatch('/<li id="[^"]+-finance-label" class="px-3 pt-4[^"]*">Finance<\/li> <li><a href="\/admin\/reports\/fees"[^>]*aria-current="page"/')
        ->and($html)->not->toContain('role="menu"')
        ->and($html)->not->toContain('role="menuitem"')
        ->and($html)->toMatch('/-academic-management-menu"[^>]*>.*>Planning<\/li>/');
});

it('renders the mobile drawer as a labelled modal dialog with its trigger', function (): void {
    $html = page('/admin/students?display=mobile');

    expect($html)->toMatch('/x-on:gcego-navigation-open.window="matches\(\$event\) && show\(\)" x-on:gcego-navigation-close.window="matches\(\$event\) && close\(\)" x-on:gcego-navigation-toggle.window="matches\(\$event\) && toggle\(\)" x-on:keydown.escape.window="close\(\)"/')
        ->and($html)->toMatch('/<button type="button" x-ref="trigger" aria-expanded="false" aria-controls="([^"]+-drawer)"[^>]*class="inline-flex size-11/')
        ->and($html)->toContain('<span class="sr-only">Open navigation</span>')
        ->and($html)->toMatch('/<div id="[^"]+-drawer" role="dialog" aria-modal="true" aria-label="Administration" hidden x-bind:hidden="! open" wire:ignore.self/')
        ->and($html)->toContain('x-on:keydown.tab="trap($event)"')
        ->and($html)->toContain('<span class="sr-only">Close navigation</span>')
        ->and($html)->toMatch('/<nav aria-label="Administration"> <ul role="list"/')
        ->and($html)->toContain('aria-current="page"')
        ->and($html)->not->toContain('x-bind:class="compact');
});

it('renders breadcrumbs as an ordered list with the current page marked', function (): void {
    $html = page('/admin/subjects/1?display=topbar');

    expect($html)->toMatch('/<nav aria-label="Breadcrumb" class="min-w-0"> <ol class="flex min-w-0 flex-wrap items-center gap-1.5 text-sm text-gcego-text-muted">/')
        ->and($html)->toMatch('/<a href="\/admin" class="[^"]+">Dashboard<\/a> <\/li>/')
        ->and($html)->toMatch('/<span class="truncate">Academic Management<\/span>/')
        ->and($html)->toMatch('/<span aria-current="page" class="truncate font-medium text-gcego-text">Chemistry<\/span>/')
        ->and(preg_match_all('/<svg [^>]*class="shrink-0 size-4 text-gcego-text-muted\/70 rtl:rotate-180" data-gcego-icon="heroicon-mini:chevron-right" aria-hidden="true" focusable="false">/', $html))->toBe(3)
        ->and(page('/admin/settings'))->not->toContain('Breadcrumb');
});

it('renders nothing for a user who can see nothing', function (): void {
    auth()->logout();
    actAs(null, null);

    expect(page('/admin'))->toBe('');
});

it('applies Tailwind slots of the navigation and of single nodes', function (): void {
    app(Registry::class)->register(new Navigation('styled',
        new Item('Dashboard', '/admin'),
        new Item('Students', '/admin/students', new Tailwind(new Slot(Navigation::ItemSlot, ['rounded-full']))),
        new Tailwind(
            new Slot(Tailwind::Wrapper, ['flex', 'flex-col', 'gap-1', 'p-3']),
            new Slot(Navigation::ItemSlot, ['flex', 'items-center', 'gap-3', 'rounded-xl', 'px-3', 'py-2.5', 'text-sm', 'font-medium', 'transition-colors']),
            new Slot(Navigation::ItemActiveSlot, ['bg-indigo-600', 'text-white']),
        ),
    ));
    onPage('/admin');
    $html = squish(navigation()->render('styled')->toHtml());

    expect($html)->toContain('class="group/nav flex min-w-0 flex-col gap-1 text-sm p-3"')
        ->and($html)->toMatch('/<a href="\/admin" class="[^"]*rounded-xl px-3 py-2.5[^"]* bg-indigo-600 text-white" aria-current="page"/')
        ->and($html)->toMatch('/<a href="\/admin\/students" class="[^"]*rounded-full[^"]*"/')
        ->and(fn () => navigation()->renderNavigation(new Navigation('bad_slot', new Item('A', '/admin', new Tailwind(new Slot('panel', ['p-4']))))))->toThrow(RenderException::class);
});

it('escapes labels, tooltips and descriptions', function (): void {
    app(Registry::class)->register(new Navigation('xss',
        new Group('<img src=x onerror=alert(1)>', new Item(new Property(Item::Label, '<script>alert(1)</script>'), new Property(Item::Url, '/admin'), new Property(Item::Tooltip, '" onmouseover="alert(1)'), new Property(Item::Description, '<b>bold</b>'))),
        new ExternalLink('"><svg onload=alert(1)>', 'https://docs.example.com'),
    ));
    onPage('/admin');
    $html = navigation()->render('xss')->toHtml();

    expect($html)->not->toContain('<script>alert')
        ->and($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('<b>bold')
        ->and($html)->not->toContain('" onmouseover')
        ->and($html)->not->toContain('<svg onload')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->toContain('title="&quot; onmouseover=&quot;alert(1)"');
});

it('adds wire:navigate to internal links when Livewire navigation is enabled', function (): void {
    config()->set('gcego-navigation.links.wire_navigate', true);
    app()->forgetInstance(NavigationConfig::class);
    app()->forgetInstance(NavigationManager::class);
    $html = page('/admin');

    expect($html)->toMatch('/<a href="\/admin"[^>]* wire:navigate data-gcego-search\s*>/')
        ->and($html)->not->toMatch('/<a href="https:\/\/docs[^>]*wire:navigate/');
});

it('generates absolute URLs when configured', function (): void {
    config()->set('gcego-navigation.links.absolute', true);
    app()->forgetInstance(NavigationConfig::class);
    Gate::define('institution.members.manage', fn (): bool => true);
    actAs($this->world['admin']);

    expect(page('/admin'))->toContain('href="http://localhost/admin/institutions"');
});
