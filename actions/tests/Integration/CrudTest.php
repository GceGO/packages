<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Tests\Fixtures\Models\Course;
use Gcego\Actions\Tests\Fixtures\Models\Student;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
});

it('creates records from validated input, setting the tenant from the verified context', function (): void {
    actAs($this->world['editor']);
    $result = act('create', ['input' => ['name' => 'Chemistry', 'code' => 'CHM']]);
    $created = Subject::query()->where('name', 'Chemistry')->first();

    expect($result->status)->toBe(ActionStatus::Success)
        ->and($result->message)->toBe('Create: saved.')
        ->and($result->recordType)->toBe(Subject::class)
        ->and($result->recordKey)->toBe($created?->id)
        ->and($created?->institution_id)->toBe(1)
        ->and($created?->code)->toBe('CHM');
});

it('returns validation errors and persists nothing for invalid input', function (): void {
    actAs($this->world['editor']);
    $result = act('create', ['input' => ['name' => '', 'code' => str_repeat('x', 40)]]);

    expect($result->status)->toBe(ActionStatus::ValidationFailed)
        ->and(array_keys($result->errors))->toBe(['name', 'code'])
        ->and(Subject::query()->count())->toBe(4);
});

it('denies create without the policy ability', function (): void {
    actAs($this->world['reader']);

    expect(act('create', ['input' => ['name' => 'X']])->status)->toBe(ActionStatus::Denied)
        ->and(Subject::query()->where('name', 'X')->exists())->toBeFalse();
});

it('edits scoped records with validated input and casts', function (): void {
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];
    $result = act('edit', ['record' => $algebra->id, 'input' => ['name' => 'Linear Algebra', 'is_published' => '1']]);

    expect($result->successful())->toBeTrue()
        ->and($algebra->refresh()->name)->toBe('Linear Algebra')
        ->and($algebra->is_published)->toBeTrue();
});

it('denies edits the policy refuses and leaves the record unchanged', function (): void {
    actAs($this->world['reader']);

    expect(act('edit', ['record' => $this->world['subjects']['algebra']->id, 'input' => ['name' => 'Hacked']])->status)->toBe(ActionStatus::Denied)
        ->and($this->world['subjects']['algebra']->refresh()->name)->toBe('Algebra');

    actAs($this->world['editor']);

    expect(act('edit', ['record' => $this->world['subjects']['locked']->id, 'input' => ['name' => 'Hacked']])->status)->toBe(ActionStatus::Denied);
});

it('treats other tenants, deleted and missing records as not found', function (string $subject): void {
    actAs($this->world['admin']);

    $key = $subject === 'missing' ? 999 : $this->world['subjects'][$subject]->id;

    expect(act('edit', ['record' => $key, 'input' => ['name' => 'X']])->status)->toBe(ActionStatus::NotFound);
})->with(['south', 'trashed', 'missing']);

it('views records without mutating them', function (): void {
    actAs($this->world['reader']);
    $before = $this->world['subjects']['algebra']->updated_at;
    $result = act('view', ['record' => $this->world['subjects']['algebra']->id]);

    expect($result->successful())->toBeTrue()
        ->and($result->data)->toBe(['intent' => 'view'])
        ->and($result->recordKey)->toBe($this->world['subjects']['algebra']->id)
        ->and($result->refresh)->toBeFalse()
        ->and($this->world['subjects']['algebra']->refresh()->updated_at?->eq($before))->toBeTrue();
});

it('requires confirmation to delete, then soft deletes', function (): void {
    actAs($this->world['admin']);
    $biology = $this->world['subjects']['biology'];
    $first = act('delete', ['record' => $biology->id]);

    expect($first->status)->toBe(ActionStatus::ConfirmationRequired)
        ->and($first->confirmation['destructive'] ?? null)->toBeTrue()
        ->and(Subject::query()->find($biology->id))->not->toBeNull();

    $second = act('delete', ['record' => $biology->id, 'confirmation' => $first->confirmation['token'] ?? '']);

    expect($second->status)->toBe(ActionStatus::Success)
        ->and($second->message)->toBe('Deleted.')
        ->and(Subject::query()->find($biology->id))->toBeNull()
        ->and(Subject::withTrashed()->find($biology->id)?->trashed())->toBeTrue();
});

it('restores and force deletes only trashed records', function (): void {
    actAs($this->world['admin']);
    $trashed = $this->world['subjects']['trashed'];

    expect(act('restore', ['record' => $this->world['subjects']['algebra']->id])->status)->toBe(ActionStatus::NotFound)
        ->and(act('restore', ['record' => $trashed->id])->successful())->toBeTrue()
        ->and($trashed->refresh()->trashed())->toBeFalse()
        ->and(confirmAndAct('force-delete', ['record' => $trashed->id])->status)->toBe(ActionStatus::NotFound);

    $trashed->delete();

    expect(confirmAndAct('force-delete', ['record' => $trashed->id])->successful())->toBeTrue()
        ->and(Subject::withTrashed()->find($trashed->id))->toBeNull();
});

it('denies restore and force delete without the abilities', function (): void {
    actAs($this->world['editor']);

    expect(act('restore', ['record' => $this->world['subjects']['trashed']->id])->status)->toBe(ActionStatus::Denied)
        ->and(act('force-delete', ['record' => $this->world['subjects']['trashed']->id])->status)->toBe(ActionStatus::Denied);
});

it('supports UUID and custom string primary keys', function (): void {
    $this->rebuildApplication(['gcego-security.tenancy.models' => []]);
    $world = world();
    $this->actingAs($world['editor']);
    $student = Student::query()->create(['name' => 'Ada']);
    (new Course)->forceFill(['code' => 'PHY-1', 'title' => 'Physics'])->save();

    expect(act('edit', ['record' => $student->id, 'input' => ['name' => 'Ada L.']], 'blueprint:students')->successful())->toBeTrue()
        ->and($student->refresh()->name)->toBe('Ada L.')
        ->and(act('edit', ['record' => 'not-a-uuid', 'input' => ['name' => 'x']], 'blueprint:students')->status)->toBe(ActionStatus::NotFound)
        ->and(act('delete', ['record' => 'PHY-1'], 'blueprint:courses')->successful())->toBeTrue()
        ->and(Course::query()->find('PHY-1'))->toBeNull();
});

it('runs custom actions with injected class handlers', function (): void {
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];

    expect(act('publish', ['record' => $algebra->id])->successful())->toBeTrue()
        ->and($algebra->refresh()->is_published)->toBeTrue()
        ->and(config('fixtures.published'))->toBe($algebra->id);

    actAs($this->world['reader']);

    expect(act('publish', ['record' => $this->world['subjects']['biology']->id])->status)->toBe(ActionStatus::Denied);
});

it('runs closure handlers with confirmation and validated input', function (): void {
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];
    $result = confirmAndAct('rename', ['record' => $algebra->id, 'input' => ['name' => 'Renamed']]);

    expect($result->successful())->toBeTrue()
        ->and($algebra->refresh()->name)->toBe('Renamed');
});

it('requires a record for record actions and refuses one for others', function (): void {
    actAs($this->world['editor']);

    expect(act('edit', ['input' => ['name' => 'x']])->reason)->toBe('record_required')
        ->and(act('create', ['record' => 1, 'input' => ['name' => 'x']])->reason)->toBe('record_not_accepted');
});

it('authorizes actions with requirement nodes inside groups', function (): void {
    Gate::define('subjects.archive', fn ($user): bool => $user->name === 'Admin');
    actAs($this->world['editor']);
    $algebra = $this->world['subjects']['algebra'];

    expect(act('archive', ['record' => $algebra->id])->status)->toBe(ActionStatus::Denied);

    actAs($this->world['admin']);

    expect(act('archive', ['record' => $algebra->id])->successful())->toBeTrue()
        ->and($algebra->refresh()->code)->toBe('ARCHIVED');
});
