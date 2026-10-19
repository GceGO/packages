# Layouts, navigation, pages, modules, panels and advanced packages

## Layouts

<!-- example:layouts -->
```php
use Gcego\Layouts\Components\Section;
use Gcego\Testing\GcegoTest;

GcegoTest::layout('dashboard')->actingAs($admin, 1)
    ->assertHealthy()
    ->assertContains(Section::class, 2)
    ->assertTabs(['overview', 'details'])
    ->assertAllowed()
    ->html()->assertSeeText('Summary');
```

`assertReports($check)` expects an inspector finding, such as `schema`, `spans`, `accessibility` or `authorization`. A layout component given directly (not registered) renders as trusted content.

## Navigation

<!-- example:navigation -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::navigation('admin')->actingAs($admin, 1)->on('/reports')
    ->assertVisible('reports')
    ->assertActive('reports')
    ->assertUrl('home', '/home')
    ->assertBreadcrumbs(['Reports']);

GcegoTest::navigation('admin')->actingAs($editor, 1)->on('/home')->assertHidden('reports');
```

`on($uri)` matches and binds the route as if that page were being rendered. The signed-in user and tenant are kept. Requirements are evaluated again for each user.

## Pages

<!-- example:pages -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::page('reports')->assertRegistered()->assertHealthy();
GcegoTest::page('reports')->actingAs($admin, 1)->assertOpens()->html()->assertSeeText('Monthly reports.');
GcegoTest::page('reports')->actingAs($editor, 1)->assertForbidden();
GcegoTest::page('reports')->actingAs(null)->assertRequiresAuthentication();
GcegoTest::page('no-such-page')->assertNotFound();
```

- **Opening**: `open()`, `assertOpens()`, `assertForbidden()` and `assertNotFound()` run `PageManager::open()`. This is the production lifecycle up to Prepared: authorization, then record lookup in scope.
- **Record pages** take the record: `assertNotFound($foreignRecord)`.

## Modules

<!-- example:modules -->
```php
use Gcego\Modules\Operation;
use Gcego\Testing\GcegoTest;

GcegoTest::module('academics')
    ->assertHasResource('courses')
    ->assertOperations('courses', [Operation::Index, Operation::Create, Operation::View, Operation::Edit])
    ->assertRouteName('courses', Operation::Edit, 'academics.courses.edit')
    ->assertHealthy();
```

## Panels

<!-- example:panels -->
```php
use Gcego\Panels\Access\PanelAccessResult;
use Gcego\Testing\GcegoTest;

GcegoTest::panel('admin')->assertPath('admin')->assertHasModule('academics')->assertRoute('academics.courses.index');
GcegoTest::panel('admin')->actingAs($admin, 1)->assertAccessible();
GcegoTest::panel('admin')->actingAs($editor, 1)->assertInaccessible(PanelAccessResult::RequirementsDenied);
```

Access is `PanelAccess::decide()`: the same decision the `gcego.panel` middleware makes on every request and every Livewire update.

## Notifications

<!-- example:notifications -->
```php
use Gcego\Notifications\Notification;
use Gcego\Testing\GcegoTest;

GcegoTest::security()->actingAs($admin, 1);
$result = Notification::make()->title('Results published')->database()->tenant('1')->now()->sendTo([$editor, $south]);

GcegoTest::notification($result)->assertSent('database', $editor)->assertSkipped('tenant', 'database', $south);
GcegoTest::inbox($editor, 1)->assertUnread(1)->assertContains('Results published');
GcegoTest::inbox($editor, 2)->assertMissing('Results published');
GcegoTest::inbox($editor)->assertChannelAuthorized($editor)->assertChannelAuthorized($admin, false);
```

- **Fakes**: use Laravel's `Notification::fake()` and `Queue::fake()` when you only need to know that something was dispatched.
- **Delivery results**: `notification()` asserts the real `DispatchResult`, including skip reasons: `tenant`, `policy`, `preference`, `duplicate`, `expired`.

## Media

<!-- example:media -->
```php
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Testing\GcegoTest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

Storage::fake('local');
$media = GcegoTest::media()->actingAs($admin, 1);
$notes = $media->upload(UploadedFile::fake()->createWithContent('notes.txt', "Lesson notes\n"));

$media->assertCan(MediaAbility::Download, $notes)
    ->assertUploadRefused(UploadedFile::fake()->createWithContent('photo.png', "<?php echo 1; ?>"), 'dangerous');

GcegoTest::media()->actingAs($south, 2)->assertNotFound($notes);
```

Uploads are validated by content, not by the claimed extension. Refusal reasons include `type`, `mismatch`, `dangerous`, `too_large`, `svg` and `denied`.

## Workflows

<!-- example:workflows -->
```php
use Gcego\Testing\GcegoTest;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Models\WorkflowInstance;

GcegoTest::security()->actingAs($editor, 1);
$instance = app(WorkflowEngine::class)->start('review', $draft, $editor);

GcegoTest::workflow($instance)
    ->assertState('draft')
    ->assertHistory(['workflow.started'])
    ->assertTransitionAvailable('submit', $editor)
    ->assertRefused(fn (WorkflowEngine $engine, WorkflowInstance $instance) => $engine->transition($instance, 'approve', $editor), 'state');
```

- **Refusal reasons**: `assertRefused()` maps the engine's exceptions to reasons. These are an `InvalidTransitionException` reason (`state`, `guard`, `comment`, `self_approval`, …), or `forbidden`, `not_found` (another tenant) or `conflict` (stale version).
- **Definitions**: `GcegoTest::workflow($workflowDefinition)` checks a definition: `assertValid()`, `assertInitialState()`, `assertEveryStateReachable()` and `assertTransition()`.

## Analytics

`GcegoTest::analytics($metricOrDataset)` covers gcego/analytics definitions: `assertValid()` (component validation plus `problems()`), `assertProblem($text)`, `assertKind($kind)` and `assertDependsOn([...])`. Report execution is not covered yet, because the analytics engine is still being built.
