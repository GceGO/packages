<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionCompleted;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Event;

it('executes an action through the production pipeline', function (): void {
    $world = world();
    Event::fake([ActionCompleted::class]);

    GcegoTest::action('subjects', 'publish')
        ->actingAs($world['admin'], 1)
        ->record($world['draft'])
        ->assertAvailable()
        ->execute()
        ->assertSuccessful();

    expect($world['draft']->refresh()->published)->toBeTrue();
    Event::assertDispatched(ActionCompleted::class);
});

it('denies an action on the server even when it is hidden from the user', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'publish')
        ->actingAs($world['editor'], 1)
        ->record($world['draft'])
        ->assertUnavailable()
        ->execute()
        ->assertDenied('policy_denied');

    expect($world['draft']->refresh()->published)->toBeFalse();
});

it('confirms destructive actions with the issued token', function (): void {
    $world = world();
    $delete = GcegoTest::action('subjects', 'delete')->actingAs($world['admin'], 1)->record($world['draft']);

    $delete->assertRequiresConfirmation()->execute()->assertConfirmationRequired();
    expect(Subject::query()->find($world['draft']->id))->not->toBeNull();

    $delete->confirmed()->execute()->assertConfirmationRequired()->assertSuccessful();
    expect(Subject::query()->find($world['draft']->id))->toBeNull();
});

it('reports validation errors by field', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'rename')->actingAs($world['admin'], 1)->record($world['algebra'])
        ->input(['name' => str_repeat('x', 60)])->execute()->assertValidationFailed(['name']);

    GcegoTest::action('subjects', 'rename')->actingAs($world['admin'], 1)->record($world['algebra'])
        ->input(['name' => 'Geometry'])->execute()->assertSuccessful();

    expect($world['algebra']->refresh()->name)->toBe('Geometry');
});

it('refuses undeclared input fields', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'rename')->actingAs($world['admin'], 1)->record($world['algebra'])
        ->input(['name' => 'Geometry', 'institution_id' => 2])->execute()->assertDenied();

    expect($world['algebra']->refresh()->institution_id)->toBe(1);
});

it('runs bulk actions and reports the counts', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'bulk-delete')->actingAs($world['admin'], 1)->select([$world['algebra'], $world['draft']])
        ->confirmed()->execute()->assertSuccessful()->assertBulk(processed: 2, selected: 2);

    expect(Subject::query()->where('institution_id', 1)->count())->toBe(0);
});

it('refuses an action on another tenant\'s record and a foreign bulk selection', function (): void {
    $world = world();

    GcegoTest::tenant(1)->actingAs($world['admin'])
        ->assertActionRefused('subjects', 'publish', $world['southern']->id)
        ->assertSelectionRefused('subjects', 'bulk-delete', [$world['southern']->id]);

    GcegoTest::action('subjects', 'bulk-delete')->actingAs($world['admin'], 1)->select([$world['algebra'], $world['southern']])
        ->confirmed()->execute()->assertDenied('selection_unavailable');

    expect(Subject::query()->count())->toBe(3);
});

it('explains an unexpected outcome', function (): void {
    $world = world();
    $message = failure(fn () => GcegoTest::action('subjects', 'publish')->actingAs($world['editor'], 1)->record($world['draft'])->execute()->assertSuccessful());

    expect($message)->toContain('GCEGO ACTION ASSERTION FAILED')->toContain('Action: publish')->toContain('status = denied');
    expect(fn () => GcegoTest::action('subjects', 'publish')->result())->toThrow(LogicException::class);
});

it('reads statuses by enum or value', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'publish')->actingAs($world['admin'], 1)->record(999)->execute()->assertNotFound()->assertStatus('not_found')->assertStatus(ActionStatus::NotFound);
});
