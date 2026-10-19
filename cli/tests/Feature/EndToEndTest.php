<?php

declare(strict_types=1);

use Gcego\Analytics\AnalyticsManager;
use Gcego\Cli\Diagnostics\Doctor;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Security\SecurityManager;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\TestingConfig;
use Gcego\Testing\TestingManager;
use Gcego\Widgets\WidgetManager;
use Livewire\Livewire;

/**
 * The framework demonstrated end to end from generated code only: an
 * application developer runs the generators, defines one Gate ability and
 * a policy (fixtures), and gets a working, authorized, tenant-scoped admin.
 */
beforeEach(function (): void {
    $steps = [
        ['gcego:make-module', ['name' => 'Academics']],
        ['gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => Subject::class, '--delete' => true]],
        ['gcego:make-form', ['name' => 'SubjectForm', '--module' => 'academics', '--model' => Subject::class]],
        ['gcego:make-table', ['name' => 'SubjectTable', '--module' => 'academics', '--model' => Subject::class]],
        ['gcego:make-panel', ['name' => 'Admin', '--module' => ['academics'], '--tenant' => 'optional']],
        ['gcego:make-action', ['name' => 'PublishSubject', '--module' => 'academics']],
        ['gcego:make-widget', ['name' => 'SubjectCount', '--model' => Subject::class]],
        ['gcego:make-metric', ['name' => 'TotalCredits', '--model' => Subject::class, '--aggregate' => 'sum', '--field' => 'credits']],
        ['gcego:make-test', ['name' => 'AcademicsModuleTest', '--type' => 'resource', '--target' => 'academics.subjects']],
    ];

    foreach ($steps as [$command, $parameters]) {
        [$code, $output] = gcego($command, $parameters);
        expect($code)->toBe(ExitCode::Success, $command.': '.$output);
    }

    // The generated action, added to the generated resource as the generator's next step says.
    $resource = ws('app/GCEGo/Modules/Academics/Resources/Subjects.php');
    file_put_contents($resource, str_replace("    new Delete,\n", "    new Delete,\n    require dirname(__DIR__).'/Actions/PublishSubject.php',\n", (string) file_get_contents($resource)));

    $this->reboot();
    $this->world = world();
});

it('discovers, validates and diagnoses the generated application', function (): void {
    expect(gcego('gcego:validate')[0])->toBe(ExitCode::Success)
        ->and(gcegoJson('gcego:routes', ['--panel' => 'admin', '--module' => 'academics'])['total'])->toBe(5)
        ->and(app(Doctor::class)->run()->result('definitions.valid')?->status->value)->toBe('pass');
});

it('serves the generated resource to permitted users only, scoped to their tenant', function (): void {
    $this->get('/admin/subjects')->assertRedirect('/login');
    asMember($this->world['reader'])->get('/admin/subjects')->assertForbidden();

    $index = asMember($this->world['editor'])->get('/admin/subjects')->assertOk();
    $index->assertSee('Chemistry')->assertSee('Physics')->assertDontSee('Biology');

    asMember($this->world['editor'])->get('/admin/subjects/'.$this->world['chemistry']->id)->assertOk()->assertSee('Chemistry');
    asMember($this->world['editor'])->get('/admin/subjects/'.$this->world['biology']->id)->assertNotFound();
    asMember($this->world['editor'])->get('/admin/subjects/create')->assertOk();
});

it('renders accessible panel pages in a real browser (axe-core)', function (): void {
    // The workspace is a temp directory: use the repository's node_modules (Playwright, axe-core).
    config(['gcego-testing.accessibility.node_modules' => dirname(__DIR__, 4).'/node_modules']);
    app()->forgetInstance(TestingConfig::class);
    app()->forgetInstance(TestingManager::class);

    if (! GcegoTest::manager()->browser()->available()) {
        $this->markTestSkipped('Node, Playwright or axe-core is not available.');
    }

    $id = $this->world['chemistry']->id;

    foreach (['/admin/subjects', '/admin/subjects/create', "/admin/subjects/{$id}", "/admin/subjects/{$id}/edit", '/admin/academics/overview'] as $uri) {
        $html = (string) asMember($this->world['editor'])->get($uri)->assertOk()->getContent();
        GcegoTest::accessibility($html)->assertAccessibleInBrowser();
    }
});

it('validates and saves form submissions through the generated form', function (): void {
    test()->actingAs($this->world['editor']);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant('1');

    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])
        ->set('data.name', '')->call('submit')->assertHasErrors(['data.name', 'data.code']);

    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])
        ->set('data.name', 'Geography')->set('data.code', 'GEO')->set('data.credits', 4)
        ->call('submit')->assertDispatched('gcego-action-completed');

    $created = Subject::query()->where('code', 'GEO')->firstOrFail();
    expect($created->institution_id)->toBe(1)->and($created->getAttribute('credits'))->toBe(4)
        ->and(fn () => Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])->set('data.api_token', 'x'))->toThrow(InvalidStateException::class);
});

it('runs the generated action only for users its policy ability allows', function (): void {
    foreach (['editor' => false, 'admin' => true] as $who => $allowed) {
        test()->actingAs($this->world[$who]);
        app()->forgetScopedInstances();
        app(SecurityManager::class)->enterTenant('1');
        $action = GcegoTest::action('page:academics-subjects-view', 'publish-subject')->actingAs($this->world[$who], 1)->record($this->world['chemistry']->id);

        $allowed
            ? $action->confirmed()->execute()->assertConfirmationRequired()->assertSuccessful()
            : $action->execute()->assertDenied('policy_denied');
    }
});

it('deletes only with the policy\'s permission, after confirmation, and never across tenants', function (): void {
    $chemistry = $this->world['chemistry']->id;

    GcegoTest::action('page:academics-subjects-view', 'delete')->actingAs($this->world['editor'], 1)->record($chemistry)->execute()->assertDenied('policy_denied');
    GcegoTest::action('page:academics-subjects-view', 'delete')->actingAs($this->world['admin'], 1)->record($this->world['biology']->id)->execute()->assertNotFound();
    GcegoTest::action('page:academics-subjects-view', 'delete')->actingAs($this->world['admin'], 1)->record($chemistry)->confirmed()->execute()->assertConfirmationRequired()->assertSuccessful();

    expect(Subject::query()->whereKey($chemistry)->exists())->toBeFalse()
        ->and(Subject::query()->whereKey($this->world['biology']->id)->exists())->toBeTrue();
});

it('renders the generated widget and calculates the generated metric for a permitted user', function (): void {
    Subject::query()->whereKey($this->world['chemistry']->id)->update(['credits' => 3]);
    Subject::query()->whereKey($this->world['physics']->id)->update(['credits' => 5]);
    Subject::query()->whereKey($this->world['biology']->id)->update(['credits' => 100]);
    test()->actingAs($this->world['editor']);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant('1');

    $html = (string) app(WidgetManager::class)->render('widget:subject_count');
    $metric = app(AnalyticsManager::class)->as($this->world['editor'], '1')->metric('total_credits')->calculate();

    expect($html)->toContain('Subject Count')->toContain('>2<')
        ->and($metric->value)->toBe(8);
});

it('runs the generated test\'s assertions', function (): void {
    $file = (string) file_get_contents(ws('tests/Feature/AcademicsModuleTest.php'));

    expect($file)->toContain("GcegoTest::module('academics')->assertHasResource('subjects')->assertHealthy();");
    GcegoTest::module('academics')->assertHasResource('subjects')->assertHealthy();
});
