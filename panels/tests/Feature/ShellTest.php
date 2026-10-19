<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Property;
use Gcego\Panels\Panel;
use Gcego\Panels\Shells\PanelShellFactory;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Support\Facades\Vite;

it('renders the sidebar shell with accessible landmarks', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/subjects'));

    expect($html)->toContain('<a href="#gcego-panel-admin-content"')
        ->toContain('Skip to content')
        ->toMatch('/<aside id="gcego-panel-admin-sidebar" aria-label="GCEGO Administration sidebar"/')
        ->toMatch('/<main id="gcego-panel-admin-content" tabindex="-1"/')
        ->toContain('<header class="sticky top-0')
        ->toContain('data-gcego-panel="admin"')
        ->toContain('<title>Subjects · GCEGO &lt;Admin&gt;</title>')
        ->and(substr_count($html, '<main'))->toBe(1);
});

it('renders the page inside the panel, with one page host', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/subjects'));

    expect(substr_count($html, 'wire:name="gcego-page"'))->toBe(1)
        ->and($html)->toContain('Chemistry')
        ->and($html)->toMatch('/<main id="gcego-panel-admin-content"[^>]*>.*wire:name="gcego-page"/');
});

it('renders branding with light and dark logos and the favicon', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain('<link rel="icon" href="/favicon.ico">')
        ->toContain('<img src="/images/logo.svg" alt="" class="h-8 w-auto shrink-0 dark:hidden">')
        ->toContain('<img src="/images/logo-dark.svg" alt="" class="h-8 w-auto shrink-0 hidden dark:block">')
        ->toContain('<a href="/admin" class="flex min-w-0 items-center gap-3');
});

it('opens the mobile drawer from the top bar (gcego/navigation\'s accessible dialog)', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toMatch('/<div class="lg:hidden"> <div id="[^"]+" x-data=/')
        ->toMatch('/role="dialog" aria-modal="true" aria-label="GCEGO Administration"/')
        ->toContain('x-on:keydown.tab="trap($event)"');
});

it('lets users collapse the desktop sidebar, remembered in their browser', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain("localStorage.getItem('gcego.panels.admin.collapsed')")
        ->toContain("x-bind:class=\"collapsed && 'lg:-ms-72 lg:has-[[data-compact]]:-ms-17 lg:invisible'\"")
        ->toMatch('/aria-controls="gcego-panel-admin-sidebar" aria-expanded="true"/');

    $this->reboot(['gcego-panels.navigation.collapsible_sidebar' => false]);
    $world = world();

    expect(html(asMember($world['admin'])->get('/admin/overview')))->not->toContain('gcego.panels.admin.collapsed');
});

it('shows the user menu with account links and sign out', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain('Signed in as Admin')
        ->toContain('<a href="/profile"')
        ->toContain('Sign out')
        ->toContain('<span class="sr-only">User menu</span>');
});

it('shows the signed-in user at the foot of the sidebar, with their initials, name, email and menu', function (): void {
    $world = world();
    $world['admin']->forceFill(['email' => 'admin@example.com'])->save();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toMatch('/<div class="flex flex-col gap-2 border-t[^"]*"> .*<\/details> <details [^>]*class="relative"> <summary class="flex min-h-12 w-full[^"]*"> <span aria-hidden="true" class="inline-flex size-8[^"]*rounded-full[^"]*"> A <\/span>.*<\/aside>/')
        ->toContain('<span class="block truncate text-sm font-medium text-gcego-text">Admin</span> <span class="block truncate text-xs text-gcego-text-muted">admin@example.com</span>')
        ->toMatch('/<div class="absolute z-40 [^"]*bottom-full start-0 mb-2 w-full group-has-\[\[data-compact\]\]\/sidebar:bottom-0 group-has-\[\[data-compact\]\]\/sidebar:start-full[^"]*"> <p class="[^"]*"> Signed in as Admin/')
        ->and(substr_count($html, 'Sign out'))->toBe(2)   // the sidebar's profile, and the top bar's menu for small screens or a collapsed sidebar
        ->and($html)->toContain('<div class="lg:hidden" x-bind:class="{ \'lg:hidden\': ! collapsed }">');
});

it('shows the user\'s picture when it is a safe URL, and initials otherwise', function (): void {
    $world = world();
    $world['admin']->setAttribute('avatar_url', '/storage/avatars/admin.png');

    expect(html(asMember($world['admin'])->get('/admin/overview')))->toContain('<img src="/storage/avatars/admin.png" alt="" class="size-full object-cover">');

    $world['admin']->setAttribute('avatar_url', 'javascript:alert(1)');
    $world['admin']->setAttribute('profile_photo_url', 'http://insecure.example.com/a.png');
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->not->toContain('javascript:')->not->toContain('insecure.example.com')
        ->toMatch('/rounded-full[^"]*"> A <\/span>/');
});

it('lets users pick light, dark or system and their own main color from the user menu', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain('<style data-gcego-accent-styles>[data-gcego-accent-swatch="default"]{')
        ->toContain('data-accents="teal blue indigo violet rose red orange amber green"')
        ->toMatch('/<button type="button" x-on:click="setMode\(\'dark\'\)" aria-pressed="false" x-bind:aria-pressed="\(mode === \'dark\'\).toString\(\)"[^>]*>/')
        ->toContain('<button type="button" x-on:click="setAccent(null)" data-gcego-accent-swatch="default"')
        ->toContain('<button type="button" x-on:click="setAccent(\'red\')" data-gcego-accent-swatch="red" aria-pressed="false" x-bind:aria-pressed="(accent === \'red\').toString()" title="Red"')
        ->toContain('<span class="sr-only">Red</span>');

    $this->reboot(['gcego-swatches.accents.enabled' => false, 'gcego-swatches.appearance.allow_switching' => false]);
    $world = world();

    expect(html(asMember($world['admin'])->get('/admin/overview')))->not->toContain('data-gcego-accent-swatch')->not->toContain('setMode(');
});

it('adds configured user menu links whose routes exist', function (): void {
    $this->reboot(['gcego-panels.user_menu' => ['Settings' => 'profile', 'Billing' => 'billing']]);
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain('Settings')->not->toContain('Billing');
});

it('lists the panels the user may switch to', function (): void {
    $world = world();
    $admin = html(asMember($world['admin'])->get('/admin/overview'));

    expect($admin)->toContain('<nav aria-label="Panels"')
        ->toMatch('/<a href="\/admin" aria-current="page"/')
        ->toContain('<a href="/staff"')
        ->toContain('<a href="/school/1"')   // in the same tenant (the admin is a member of institution 1)
        ->not->toContain('/vault');

    // A session tenant the admin is not a member of is refused (gcego/security), so nothing is listed.
    asMember($world['admin'], 2)->get('/admin/overview')->assertNotFound();

    // The editor may enter only the staff room: no switcher.
    expect(html(asMember($world['editor'])->get('/staff')))->not->toContain('aria-label="Panels"');
});

it('renders the topbar layout with the tenant', function (): void {
    $world = world();
    $html = html(asMember($world['reader'])->get('/school/1/subjects'));

    expect($html)->toContain('<div class="hidden min-w-0 flex-1 lg:block"> <nav id=')
        ->not->toContain('<aside')
        ->toContain('Workspace: 1')
        ->toContain('<a href="/school/1/subjects"');
});

it('renders the compact layout and the home of panels without a dashboard', function (): void {
    $world = world();
    $html = html(asMember($world['editor'])->get('/staff'));

    expect($html)->toContain('lg:w-60')
        ->toContain('Welcome to Staff Room')
        ->toContain('Use the navigation to get started.')
        ->toContain('<title>Staff Room · Staff Room</title>');
});

it('narrows the sidebar to an icon rail while its navigation is compact', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toMatch('/<aside id="[^"]+" aria-label="[^"]+" class="group\/sidebar [^"]*lg:w-72 lg:has-\[\[data-compact\]\]:w-17 lg:transition-all lg:duration-300 lg:ease-in-out/')
        ->toContain('group-has-[[data-compact]]/sidebar:opacity-0');
});

it('applies a remembered collapsed sidebar before the first paint, with the CSP nonce', function (): void {
    Vite::useCspNonce('n0nce');
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain('<script data-gcego-appearance-controller')
        ->toMatch('/<script data-gcego-appearance-controller[^>]* nonce="n0nce">/')
        ->toMatch('/<\/aside> <script nonce="n0nce">\(function \(\) \{ try \{ if \(localStorage\.getItem\(\'gcego\.panels\.admin\.collapsed\'\) !== \'1\'\)/')
        ->toContain('sidebar.dataset.gcegoPreset !== undefined) { sidebar.classList.remove');
});

it('applies the panel\'s own slot classes over the presets', function (): void {
    $world = world();
    routePanel(new Panel(
        new Property(Panel::Name, 'styled'),
        new Authenticated,
        new Tailwind(new Slot(Panel::SidebarSlot, ['lg:w-96']), new Slot(Tailwind::Content, ['bg-gcego-surface'])),
    ));
    $html = html(asMember($world['admin'])->get('/styled'));

    expect($html)->toContain('lg:w-96')->not->toContain('lg:w-72')
        ->toMatch('/<main id="gcego-panel-styled-content" tabindex="-1" class="[^"]*bg-gcego-surface/');
});

it('builds no shell outside panels', function (): void {
    expect(app(PanelShellFactory::class)->current('Anything'))->toBeNull();
});

it('uses gcego/pages\' own layout for page routes outside panels', function (): void {
    $this->reboot(['gcego-panels.routing.register_page_routes' => false]);
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/help'));

    expect($html)->toContain('How to use the portal.')->not->toContain('data-gcego-panel');
});
