<?php

declare(strict_types=1);

use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Cache;

it('counts the queries of a callback only', function (): void {
    world();
    Subject::query()->count();

    $performance = GcegoTest::performance()->measure(fn (): int => Subject::query()->count());

    $performance->assertQueryCount(1)->assertQueryCountAtMost(1)->assertNoDuplicateQueries();
    expect($performance->result())->toBe(3)->and($performance->measurement()->sql()[0])->toContain('select count(*)');

    Subject::query()->count();
    $performance->assertQueryCount(1);
});

it('detects duplicate queries and the N+1 shape', function (): void {
    world();

    $performance = GcegoTest::performance()->measure(function (): void {
        foreach (Subject::query()->pluck('id') as $id) {
            Subject::query()->whereKey($id)->first();
            Subject::query()->whereKey($id)->first();
        }
    });

    expect($performance->measurement()->duplicates())->toHaveCount(3)
        ->and($performance->measurement()->repeatedShapes(5))->toHaveCount(1);

    expect(failure(fn () => $performance->assertNoRepeatedQueries()))->toContain('likely N+1')->toContain('select * from "subjects" where "subjects"."id" = ? limit ?');
    expect(failure(fn () => $performance->assertQueryCountAtMost(3)))->toContain('queries = 7');
});

it('measures memory, duration and payload size', function (): void {
    GcegoTest::performance()->measure(fn (): string => str_repeat('x', 2048))
        ->assertMemoryBelow(50 * 1024 * 1024)
        ->assertDurationBelow(5000)
        ->assertPayloadBelow(4096);

    expect(failure(fn () => GcegoTest::performance()->measure(fn (): string => str_repeat('x', 2048))->assertPayloadBelow(1024)))->toContain('2 KB');
});

it('asserts a cache hit on the second run', function (): void {
    world();

    GcegoTest::performance()->assertCachedAfterFirstRun(fn (): mixed => Cache::remember('subjects.count', 60, fn (): int => Subject::query()->count()));
    expect(failure(fn () => GcegoTest::performance()->assertCachedAfterFirstRun(fn (): int => Subject::query()->count())))->toContain('served from the cache');
});

it('offers PHPUnit-style performance assertions', function (): void {
    world();

    $this->assertGcegoQueryCountAtMost(2, fn (): int => Subject::query()->count());
    $this->assertGcegoNoRepeatedQueries(fn (): int => Subject::query()->count());
});
