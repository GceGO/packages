<?php

declare(strict_types=1);

use Gcego\Data\Query\Query;
use Gcego\Data\Source;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Records\RecordAuthorizer;
use Gcego\Security\Resources\ResourceAbility;
use Gcego\Security\Resources\ResourceAccess;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Gcego\Security\Tests\Fixtures\Models\Report;
use Gcego\Security\Tests\Fixtures\Models\Student;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    $this->actingAs($this->world['teacher']);
    security()->enterTenant((string) $this->world['north']->id);
});

function denial(Closure $callback): ?int
{
    try {
        $callback();
    } catch (AccessDeniedException $exception) {
        return $exception->status();
    }

    return null;
}

it('resolves authorized records in scope', function (): void {
    expect(security()->record(new Source(Subject::class), $this->world['subjects']['algebra']->id)->name)->toBe('Algebra');
});

it('answers 403 for in-scope records the policy denies, or 404 when concealing', function (): void {
    $draft = $this->world['subjects']['draft']->id;

    expect(denial(fn () => security()->record(new Source(Subject::class), $draft)))->toBe(403)
        ->and(denial(fn () => security()->record(new Source(Subject::class), $draft, strategy: DenialStrategy::Conceal)))->toBe(404)
        ->and(denial(fn () => security()->record(new Source(Subject::class), $this->world['subjects']['algebra']->id, 'update')))->toBe(403);
});

it('answers 404 for other tenants, missing, soft-deleted and malformed keys', function (string|int $key): void {
    expect(denial(fn () => security()->record(new Source(Subject::class), $key)))->toBe(404);
})->with([
    'another institution' => fn () => $this->world['subjects']['south']->id,
    'missing' => [999],
    'soft-deleted' => fn () => $this->world['subjects']['trashed']->id,
    'malformed' => ['1 OR 1=1'],
]);

it('resolves UUID records only inside the tenant', function (): void {
    Gate::define('view', fn (User $user, mixed $record): bool => $record instanceof Student);

    expect(security()->record(new Source(Student::class), $this->world['students']['north']->id)->name)->toBe('Ada')
        ->and(denial(fn () => security()->record(new Source(Student::class), $this->world['students']['south']->id)))->toBe(404)
        ->and(denial(fn () => security()->record(new Source(Student::class), 'not-a-uuid')))->toBe(404);
});

it('keeps source constraints during record resolution', function (): void {
    $published = new Source(Subject::class, new Query(fn ($query) => $query->where('is_published', true)));
    $this->actingAs(User::query()->create(['name' => 'Editor']));
    Membership::query()->create(['user_id' => auth()->id(), 'institution_id' => $this->world['north']->id, 'role' => 'teacher']);
    newRequest();
    security()->enterTenant((string) $this->world['north']->id);

    expect(denial(fn () => security()->record($published, $this->world['subjects']['draft']->id, 'update')))->toBe(404)
        ->and(security()->record(new Source(Subject::class), $this->world['subjects']['draft']->id, 'update')->name)->toBe('Draft Physics');
});

it('denies records of models without a policy or requirement', function (): void {
    $this->rebuildApplication(['gcego-security.tenancy.models' => []]);
    $report = Report::query()->create(['title' => 'Q1']);
    $this->actingAs(User::query()->create(['name' => 'Someone']));

    expect(denial(fn () => app(RecordAuthorizer::class)->resolve(new Source(Report::class), $report->id)))->toBe(403)
        ->and(app(RecordAuthorizer::class)->inspect(new Source(Report::class), $report->id)[0]->reason?->value)->toBe('no_policy');
});

it('applies conventional module abilities with policies and permissions', function (): void {
    $access = new ResourceAccess(Subject::class, permission: 'subjects', tenant: true, permissions: ['viewAny' => 'subjects.view']);
    $algebra = $this->world['subjects']['algebra'];

    expect(security()->resource($access, ResourceAbility::ViewAny)->allowed)->toBeTrue()
        ->and(security()->resource($access, ResourceAbility::View, $algebra)->allowed)->toBeTrue()
        ->and(security()->resource($access, ResourceAbility::Update, $algebra)->reason?->value)->toBe('missing_permission')
        ->and(security()->resource(new ResourceAccess(Subject::class), ResourceAbility::View, $algebra)->allowed)->toBeTrue()
        ->and(security()->resource(new ResourceAccess(Report::class), ResourceAbility::ViewAny)->reason?->value)->toBe('no_policy')
        ->and(security()->resource(new ResourceAccess(Report::class, permission: 'reports'), ResourceAbility::Create)->reason?->value)->toBe('missing_permission')
        ->and(fn () => security()->resource($access, ResourceAbility::Update))->toThrow(InvalidPermissionException::class, 'needs a Subject record');
});

it('decides field access server-side', function (): void {
    Gate::define('students.identity.view', fn (User $user): bool => $user->name === 'Admin');
    $student = $this->world['students']['north'];
    $fields = security()->fields([
        'name',
        new FieldRule('national_id', read: new Permission('students.identity.view'), mask: true),
        new FieldRule('institution_id', write: new Permission('students.transfer')),
        new FieldRule('notes', read: new Permission('students.notes.view')),
    ], $student);

    expect($fields->toArray())->toBe(['name' => 'editable', 'national_id' => 'redacted', 'institution_id' => 'readable', 'notes' => 'hidden'])
        ->and($fields->editable())->toBe(['name'])
        ->and($fields->readable())->toBe(['name', 'institution_id'])
        ->and($fields->access('undeclared'))->toBe(FieldAccess::Hidden)
        ->and(app(FieldAuthorizer::class)->visible(['name' => 'Ada', 'national_id' => 'NAT-1', 'institution_id' => 1, 'notes' => 'x', 'extra' => 'y'], $fields))
        ->toBe(['name' => 'Ada', 'national_id' => '********', 'institution_id' => 1]);
});

it('rejects writes to read-only, hidden and undeclared fields', function (array $input): void {
    $fields = security()->fields(['name', new FieldRule('institution_id', write: new Permission('students.transfer')), new FieldRule('notes', read: new Permission('x.y'))]);

    expect(app(FieldAuthorizer::class)->writable(['name' => 'Ok'], $fields))->toBe(['name' => 'Ok'])
        ->and(fn () => app(FieldAuthorizer::class)->writable($input, $fields))->toThrow(AccessDeniedException::class);
})->with([
    'read-only field' => [['name' => 'x', 'institution_id' => 2]],
    'hidden field' => [['notes' => 'x']],
    'mass assignment' => [['name' => 'x', 'is_admin' => true]],
]);

it('never serializes hidden attributes through visible()', function (): void {
    $student = $this->world['students']['north'];
    $fields = security()->fields(['name', 'national_id']);

    expect(app(FieldAuthorizer::class)->visible($student, $fields))->toBe(['name' => 'Ada', 'national_id' => null]);
});
