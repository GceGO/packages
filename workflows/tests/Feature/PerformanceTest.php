<?php

declare(strict_types=1);

use Gcego\Workflows\Tasks\Inbox;
use Gcego\Workflows\Tests\Fixtures\Models\Application;

it('keeps a transition to a bounded number of queries', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    actAs($world['editor']);

    $queries = queriesDuring(static fn () => engine()->transition($instance, 'submit', $world['editor']));

    expect(count($queries))->toBeLessThanOrEqual(25);
});

it('builds an inbox of many tasks without per-task membership queries', function (): void {
    $world = world();

    for ($i = 0; $i < 30; $i++) {
        $application = Application::query()->create(['name' => 'Bulk '.$i]);
        inReview([...$world, 'alpha' => $application], 'alpha');
    }

    actAs($world['reviewer1']);
    $started = hrtime(true);
    $queries = queriesDuring(static function () use ($world, &$page): void {
        $page = app(Inbox::class)->for($world['reviewer1']);
    });
    $milliseconds = (hrtime(true) - $started) / 1e6;

    expect($page->total())->toBe(30)->and($page->count())->toBe(15)
        ->and(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'memberships')))->toHaveCount(1)
        ->and($milliseconds)->toBeLessThan(3000);
});
