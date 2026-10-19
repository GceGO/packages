<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Tests\Fixtures\Models\Subject;

beforeEach(function (): void {
    $this->world = world();
});

it('bulk deletes an authorized selection after confirmation', function (): void {
    actAs($this->world['admin']);
    $keys = [$this->world['subjects']['algebra']->id, $this->world['subjects']['biology']->id];

    $first = act('bulk-delete', ['selection' => $keys]);
    $result = act('bulk-delete', ['selection' => $keys, 'confirmation' => $first->confirmation['token'] ?? '']);

    expect($first->status)->toBe(ActionStatus::ConfirmationRequired)
        ->and($result->successful())->toBeTrue()
        ->and($result->data)->toBe(['selected' => 2, 'processed' => 2, 'skipped' => 0, 'failed' => 0])
        ->and($result->message)->toBe('2 of 2 records deleted.')
        ->and(Subject::query()->whereKey($keys)->count())->toBe(0);
});

it('processes nothing when the user may not act on every record', function (): void {
    actAs($this->world['admin']);
    $keys = [$this->world['subjects']['algebra']->id, $this->world['subjects']['locked']->id];

    expect(confirmAndAct('bulk-delete', ['selection' => $keys])->status)->toBe(ActionStatus::Denied)
        ->and(Subject::query()->whereKey($keys)->count())->toBe(2);
});

it('never touches records of another institution submitted in the selection', function (): void {
    actAs($this->world['admin']);
    $keys = [$this->world['subjects']['algebra']->id, $this->world['subjects']['south']->id];
    $result = confirmAndAct('bulk-delete', ['selection' => $keys]);

    expect($result->status)->toBe(ActionStatus::Denied)
        ->and($result->message)->toBe('Some selected records cannot be processed (1). Nothing was changed.')
        ->and(Subject::query()->withoutGlobalScopes()->whereKey($keys)->whereNull('deleted_at')->count())->toBe(2);
});

it('denies the bulk action itself without the ability', function (): void {
    actAs($this->world['editor']);

    expect(confirmAndAct('bulk-delete', ['selection' => [$this->world['subjects']['algebra']->id]])->status)->toBe(ActionStatus::Denied);
});

it('rejects invalid and oversized selections', function (array $selection, string $message): void {
    actAs($this->world['admin']);

    $result = act('bulk-delete', ['selection' => $selection]);

    expect($result->reason)->toBe('invalid_selection')
        ->and($result->message)->toBe($message);
})->with([
    'empty' => [[], 'Select at least one record.'],
    'nested' => [[['1']], 'The selection contains an invalid key.'],
    'too many' => [range(1, 501), 'Select at most 500 records.'],
    'control characters' => [["1\0"], 'The selection contains an invalid key.'],
]);

it('processes allowed records and reports the rest under the partial policy', function (): void {
    actAs($this->world['editor']);
    $broken = $this->world['subjects']['biology'];
    $broken->forceFill(['code' => 'BROKEN'])->save();
    $keys = [$this->world['subjects']['algebra']->id, $broken->id, $this->world['subjects']['south']->id, 9999];

    $result = act('bulk-publish', ['selection' => $keys]);

    expect($result->successful())->toBeTrue()
        ->and($result->data)->toBe(['selected' => 4, 'processed' => 1, 'skipped' => 2, 'failed' => 1])
        ->and($this->world['subjects']['algebra']->refresh()->is_published)->toBeTrue()
        ->and($broken->refresh()->is_published)->toBeFalse()
        ->and(Subject::query()->withoutGlobalScopes()->find($this->world['subjects']['south']->id)?->is_published)->toBeFalse();
});

it('resolves selections in chunks through the scoped source', function (): void {
    $this->rebuildApplication(['gcego-actions.bulk.chunk_size' => 2]);
    $world = world();
    actAs($world['editor']);
    $keys = Subject::query()->pluck('id')->all();

    $queries = queriesDuring(fn () => act('bulk-publish', ['selection' => $keys]));
    $selects = array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, '"subjects"."id" in'));

    expect(count($keys))->toBe(4)
        ->and(count($selects))->toBe(4)
        ->and(array_filter($selects, fn (string $sql): bool => ! str_contains($sql, '"subjects"."institution_id" = ?')))->toBe([]);
});

it('binds the confirmation to the exact selection', function (): void {
    actAs($this->world['admin']);
    $first = act('bulk-delete', ['selection' => [$this->world['subjects']['algebra']->id]]);

    expect(act('bulk-delete', ['selection' => [$this->world['subjects']['algebra']->id, $this->world['subjects']['biology']->id], 'confirmation' => $first->confirmation['token'] ?? ''])->status)->toBe(ActionStatus::Conflict)
        ->and(Subject::query()->whereKey([$this->world['subjects']['algebra']->id, $this->world['subjects']['biology']->id])->count())->toBe(2);
});

it('rolls back the whole run when one record fails under all-or-nothing', function (): void {
    actAs($this->world['admin']);
    Subject::deleting(fn (Subject $subject): bool => $subject->code !== 'BIO');
    $keys = [$this->world['subjects']['algebra']->id, $this->world['subjects']['biology']->id];

    $result = confirmAndAct('bulk-delete', ['selection' => $keys]);

    expect($result->status)->toBe(ActionStatus::Failed)
        ->and(Subject::query()->whereKey($keys)->count())->toBe(2);
});

it('handles the maximum selection within a regression budget', function (): void {
    actAs($this->world['editor']);
    $now = now()->toDateTimeString();
    Subject::query()->insert(array_map(fn (int $i): array => ['name' => 'Bulk '.$i, 'institution_id' => 1, 'created_at' => $now, 'updated_at' => $now], range(1, 496)));
    $keys = Subject::query()->pluck('id')->all();
    $start = hrtime(true);

    $result = act('bulk-publish', ['selection' => $keys]);
    $elapsed = (hrtime(true) - $start) / 1e6;

    expect(count($keys))->toBe(500)
        ->and($result->data['processed'] ?? null)->toBe(499)
        ->and($elapsed)->toBeLessThan(15000.0);

    fwrite(STDERR, sprintf("\n[measured] bulk-publish of 500 selected records (499 processed): %.1f ms\n", $elapsed));
});
