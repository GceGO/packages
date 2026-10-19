<?php

declare(strict_types=1);

use Gcego\Workflows\Approvals\Decision;
use Illuminate\Support\Facades\DB;

it('notifies assignees in the instance\'s tenant once the work is committed', function (): void {
    $world = world();
    $instance = inReview($world);

    $notification = $world['reviewer1']->notifications()->first();

    expect($notification?->getAttribute('gcego_tenant'))->toBe('1')->and($notification?->getAttribute('gcego_category'))->toBe('workflows')
        ->and($notification?->data['title'] ?? null)->toBe('gcego-workflows::messages.notifications.approval.title')
        ->and($notification?->data['parameters']['workflow'] ?? null)->toBe('Institution Registration')
        ->and($world['south']->notifications()->count())->toBe(0)
        ->and(json_encode($notification?->data))->not->toContain('Alpha');
});

it('sends nothing when the command rolls back', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);
    actAs($world['reviewer1']);

    try {
        DB::transaction(static function () use ($instance, $world): void {
            engine()->transition($instance, 'review', $world['reviewer1']);

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('notifications')->count())->toBe(0);
});

it('tells the initiator when the workflow finishes', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Reject, 'Incomplete.');

    expect($world['editor']->notifications()->where('data->title', 'gcego-workflows::messages.notifications.completed.title')->exists())->toBeTrue();
});

it('can be switched off', function (): void {
    $this->reboot(['gcego-workflows.notifications.enabled' => false]);
    $world = world();
    inReview($world);

    expect(DB::table('notifications')->count())->toBe(0);
});
