<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Notifications\Notification;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('asserts notification deliveries and tenant-scoped inboxes', function (): void {
    $world = world();
    GcegoTest::security()->actingAs($world['admin'], 1);

    $result = Notification::make()->title('Results published')->database()->tenant('1')->now()->sendTo([$world['editor'], $world['south']]);

    GcegoTest::notification($result)
        ->assertSent('database', $world['editor'])
        ->assertSkipped('tenant', 'database', $world['south'])
        ->assertNoFailures()
        ->assertDeliveryCount(2);

    GcegoTest::inbox($world['editor'], 1)->assertUnread(1)->assertContains('Results published');
    GcegoTest::inbox($world['editor'], 2)->assertMissing('Results published');
    GcegoTest::inbox($world['south'], 2)->assertUnread(0);
});

it('authorizes private notification channels for their owner only', function (): void {
    $world = world();

    GcegoTest::inbox($world['editor'])
        ->assertChannelAuthorized($world['editor'])
        ->assertChannelAuthorized($world['admin'], false)
        ->assertChannelAuthorized(null, false);
});

it('validates uploads by content and scopes media to the tenant', function (): void {
    Storage::fake('local');
    $world = world();
    $media = GcegoTest::media()->actingAs($world['admin'], 1);

    $notes = $media->upload(UploadedFile::fake()->createWithContent('notes.txt', "Lesson notes\n"));
    $media->assertFound($notes)->assertCan(MediaAbility::Download, $notes)
        ->assertUploadRefused(UploadedFile::fake()->createWithContent('photo.png', "<?php echo 'x'; ?>"), 'dangerous')
        ->assertUploadRefused(UploadedFile::fake()->createWithContent('photo.png', "Lesson notes\n"), 'mismatch')
        ->assertUploadRefused(UploadedFile::fake()->createWithContent('shell.php', "<?php echo 'x'; ?>"));

    GcegoTest::media()->actingAs($world['south'], 2)->assertNotFound($notes)->assertNotFound('01HZZZZZZZZZZZZZZZZZZZZZZZ');
    GcegoTest::media()->actingAs($world['editor'], 1)->assertCannot(MediaAbility::Delete, $notes);
});

it('follows a workflow instance through its transitions and refusals', function (): void {
    $world = world();
    GcegoTest::security()->actingAs($world['editor'], 1);
    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(Definitions::review(), $world['draft'], $world['editor']);

    $workflow = GcegoTest::workflow($instance);
    $workflow->assertState('draft')->assertStatus(InstanceStatus::Active)->assertHistory(['workflow.started'])
        ->assertTransitionAvailable('submit', $world['editor'])
        ->assertTransitionUnavailable('approve', $world['editor'])
        ->assertRefused(fn (WorkflowEngine $engine, WorkflowInstance $instance) => $engine->transition($instance, 'approve', $world['editor']), 'state');

    $engine->transition($instance, 'submit', $world['editor']);
    $workflow->assertState('submitted')->assertHistory(['workflow.started', 'workflow.transitioned'], exact: false)
        ->assertRefused(fn (WorkflowEngine $engine, WorkflowInstance $instance) => $engine->transition($instance, 'approve', $world['editor']), 'forbidden');

    GcegoTest::security()->actingAs($world['south'], 2);
    $workflow->assertRefused(fn (WorkflowEngine $engine, WorkflowInstance $instance) => $engine->transition($instance, 'approve', $world['south']), 'not_found');

    GcegoTest::security()->actingAs($world['admin'], 1);
    $engine->transition($instance, 'approve', $world['admin']);
    $workflow->assertState('approved')->assertStatus(InstanceStatus::Completed);
});

it('checks a workflow definition before it runs', function (): void {
    GcegoTest::workflow(Definitions::review())
        ->assertValid()
        ->assertInitialState('draft')
        ->assertEveryStateReachable()
        ->assertTransition('submit', 'draft', 'submitted');

    $broken = new Workflow('broken', 'Broken', new State('a', 'A'), new State('orphan', 'Orphan'), new Transition('go', 'a', 'missing'));

    expect(failure(fn () => GcegoTest::workflow($broken)->assertValid()))->toContain('missing');
    failure(fn () => GcegoTest::workflow($broken)->assertEveryStateReachable());
});

it('checks analytics definitions when the package is installed', function (): void {
    $metric = new Metric('subjects', model: Subject::class);

    GcegoTest::analytics($metric)->assertValid()->assertKind('count');
})->skip(! class_exists(Metric::class), 'gcego/analytics is not installed.');
