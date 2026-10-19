<?php

declare(strict_types=1);

use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Layout;
use Gcego\Modules\Operation;
use Gcego\Pages\PageManager;
use Gcego\Panels\Access\PanelAccessResult;
use Gcego\Security\SecurityConfig;
use Gcego\Testing\GcegoTest;

it('asserts layout structure, findings and rendering', function (): void {
    $world = world();

    GcegoTest::layout('dashboard')->actingAs($world['admin'], 1)
        ->assertHealthy()
        ->assertContains(Section::class, 2)
        ->assertContains(Grid::class)
        ->assertTabs(['overview', 'details'])
        ->assertAllowed()
        ->html()->assertSeeText('Summary')->assertSeeText('Activity');

    GcegoTest::layout('dashboard')->actingAs(null)->assertDenied();
});

it('reports layout problems the inspector finds', function (): void {
    GcegoTest::layout(new Layout('broken', new Main))->assertReports('schema')->assertReports('authorization');

    expect(failure(fn () => GcegoTest::layout(new Layout('broken', new Main))->assertHealthy()))->toContain('must be a direct child of Split or Sidebar');
});

it('resolves navigation for the acting user on a page', function (): void {
    $world = world();

    GcegoTest::navigation('admin')->actingAs($world['admin'], 1)->on('/reports')
        ->assertVisible('home')
        ->assertVisible('reports')
        ->assertActive('reports')
        ->assertUrl('home', '/home')
        ->assertBreadcrumbs(['Reports'])
        ->assertHealthy();

    GcegoTest::navigation('admin')->actingAs($world['editor'], 1)->on('/home')
        ->assertActive('home')
        ->assertHidden('reports');
});

it('opens pages with the production lifecycle', function (): void {
    $world = world();

    GcegoTest::page('reports')->assertRegistered()->assertHealthy();
    GcegoTest::page('reports')->actingAs($world['admin'], 1)->assertOpens()->html()->assertSeeText('Monthly reports.');
    GcegoTest::page('reports')->actingAs($world['editor'], 1)->assertForbidden();
    GcegoTest::page('reports')->actingAs(null)->assertRequiresAuthentication();
    GcegoTest::page('no-such-page')->assertNotFound();
});

it('opens generated record pages only for records in scope', function (): void {
    $world = world();
    $page = GcegoTest::module('academics')->definition()->resource('courses')?->pageName(Operation::Edit) ?? 'missing';
    $identity = PageManager::class;

    GcegoTest::page($page)->actingAs($world['admin'], 1)
        ->assertOpens($world['algebra'])
        ->assertNotFound($world['southern'])
        ->assertNotFound(999);

    GcegoTest::page($page)->actingAs($world['student'], 1)->assertForbidden($world['algebra']);
    expect($identity)->toBeString();
});

it('asserts the module a resource compiles to', function (): void {
    GcegoTest::module('academics')
        ->assertRegistered()
        ->assertHasResource('courses')
        ->assertOperations('courses', [Operation::Index, Operation::Create, Operation::View, Operation::Edit])
        ->assertPage('courses', Operation::Index)
        ->assertRouteName('courses', Operation::Edit, 'academics.courses.edit')
        ->assertHealthy();
});

it('asserts panel registration, routes and access', function (): void {
    $world = world();

    GcegoTest::panel('admin')
        ->assertRegistered()
        ->assertPath('admin')
        ->assertHasModule('academics')
        ->assertTenancy('optional')
        ->assertRoute('home')
        ->assertRoute('academics.courses.index')
        ->assertHealthy();

    GcegoTest::panel('admin')->actingAs($world['admin'], 1)->assertAccessible();
    GcegoTest::panel('admin')->actingAs($world['editor'], 1)->assertInaccessible(PanelAccessResult::RequirementsDenied);
    expect(fn () => GcegoTest::panel('admin')->actingAs(null)->assertAccessible())->toThrow(LogicException::class, 'signed-in user');
});

it('enforces panel access over HTTP too', function (): void {
    $world = world();
    $tenant = [app(SecurityConfig::class)->tenantSessionKey => '1'];

    $this->actingAs($world['editor'])->withSession($tenant)->get('/admin/academics/courses')->assertForbidden();
    $this->actingAs($world['admin'])->withSession($tenant)->get('/admin/academics/courses')->assertOk()->assertSee('Algebra')->assertDontSee('Southern');
});
