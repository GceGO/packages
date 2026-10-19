<?php

declare(strict_types=1);

use Gcego\Data\Contracts\DataAuthorizer;
use Gcego\Data\DataManager;
use Gcego\Data\Exceptions\RecordNotFoundException;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Filters\ValueType;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Results\DataResult;
use Gcego\Data\Search\Search;
use Gcego\Data\Sorting\Sortable;
use Gcego\Data\Source;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\Integration\SecurityDataAuthorizer;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Tests\Fixtures\Models\Report;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Gcego\Security\Tests\Fixtures\PublishedOnlyScope;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    $this->world = world();
    $this->source = new Source(
        Subject::class,
        new Filterable('institution', [FilterOperator::Equals, FilterOperator::In], ValueType::Integer, column: 'institution_id'),
        new Filterable('published', type: ValueType::Boolean, column: 'is_published'),
        new Search('name'),
        new Sortable('name', 'id'),
        new Paginate(1),
    );
});

/**
 * @param  array<mixed>  $input
 * @return list<string>
 */
function names(Source $source, array $input = []): array
{
    $names = [];

    foreach (app(DataManager::class)->query($source, $input)->items() as $subject) {
        $names[] = $subject->name;
    }

    return $names;
}

function asTeacherOf(User $user, int $institution): void
{
    test()->actingAs($user);
    newRequest();
    security()->enterTenant((string) $institution);
}

it('replaces the data package defaults', function (): void {
    expect(app(DataAuthorizer::class))->toBeInstanceOf(SecurityDataAuthorizer::class);
});

it('lists only the active tenant\'s records and none without a tenant', function (): void {
    $this->actingAs($this->world['teacher']);

    expect(fn () => app(DataManager::class)->query($this->source))->toThrow(AuthorizationException::class)
        ->and(app(DataManager::class)->query($this->source, context: QueryContext::system())->isEmpty())->toBeTrue();

    asTeacherOf($this->world['teacher'], $this->world['north']->id);

    expect(app(DataManager::class)->query($this->source, ['per_page' => 50])->pagination->total)->toBe(2)
        ->and(names($this->source, ['per_page' => 50, 'sort' => 'name']))->toBe(['Algebra', 'Draft Physics']);
});

it('keeps the tenant scope through filters, search, sorting and pagination', function (array $input, array $expected): void {
    asTeacherOf($this->world['teacher'], $this->world['north']->id);

    expect(names($this->source, $input))->toBe($expected);
})->with([
    'forged tenant filter' => [['filter' => ['institution' => '2']], []],
    'tenant list including another' => [['filter' => ['institution' => ['in' => ['1', '2']]], 'per_page' => 50, 'sort' => 'name'], ['Algebra', 'Draft Physics']],
    'search for another tenant\'s record' => [['search' => 'South'], []],
    'sort cannot reveal' => [['sort' => '-id', 'per_page' => 50], ['Draft Physics', 'Algebra']],
    'page past the end' => [['page' => 3], []],
    'page 2' => [['page' => 2, 'sort' => 'name'], ['Draft Physics']],
]);

it('keeps the tenant scope for cursor pagination', function (): void {
    asTeacherOf($this->world['teacher'], $this->world['north']->id);
    $source = new Source(Subject::class, Paginate::cursor(1));
    $first = app(DataManager::class)->query($source);
    $second = app(DataManager::class)->query($source, ['cursor' => $first->pagination->nextCursor]);

    expect(pluck_names($first))->toBe(['Algebra'])
        ->and(pluck_names($second))->toBe(['Draft Physics'])
        ->and($second->pagination->nextCursor)->toBeNull();
});

function pluck_names(DataResult $result): array
{
    return array_map(fn (Subject $subject): string => $subject->name, $result->items());
}

it('scopes record lookups and denies another tenant\'s known identifier', function (): void {
    asTeacherOf($this->world['teacher'], $this->world['north']->id);

    expect(app(DataManager::class)->find($this->source, $this->world['subjects']['algebra']->id)->found())->toBeTrue()
        ->and(app(DataManager::class)->find($this->source, $this->world['subjects']['south']->id)->found())->toBeFalse()
        ->and(fn () => app(DataManager::class)->findOrFail($this->source, $this->world['subjects']['south']->id))->toThrow(RecordNotFoundException::class);
});

it('switches the visible data with the tenant, never mixing them', function (): void {
    asTeacherOf($this->world['admin'], $this->world['north']->id);
    $north = names($this->source, ['per_page' => 50, 'sort' => 'name']);
    asTeacherOf($this->world['admin'], $this->world['south']->id);
    $south = names($this->source, ['per_page' => 50, 'sort' => 'name']);

    expect($north)->toBe(['Algebra', 'Draft Physics'])
        ->and($south)->toBe(['South Biology']);
});

it('applies role-specific scope providers', function (): void {
    security()->scope(new PublishedOnlyScope);
    asTeacherOf($this->world['assistant'], $this->world['north']->id);

    expect(names($this->source, ['per_page' => 50]))->toBe(['Algebra'])
        ->and(app(DataManager::class)->find($this->source, $this->world['subjects']['draft']->id)->found())->toBeFalse();

    asTeacherOf($this->world['teacher'], $this->world['north']->id);

    expect(names($this->source, ['per_page' => 50, 'sort' => 'name']))->toBe(['Algebra', 'Draft Physics']);
});

it('enforces requirement nodes declared on sources', function (): void {
    $guarded = new Source(Subject::class, Permission::inTenant('subjects.manage'));
    asTeacherOf($this->world['teacher'], $this->world['north']->id);

    expect(fn () => app(DataManager::class)->query($guarded))->toThrow(AuthorizationException::class);

    asTeacherOf($this->world['admin'], $this->world['north']->id);

    expect(app(DataManager::class)->query($guarded)->pagination->total)->toBe(2)
        ->and(array_filter($guarded->configurations()->all(), fn ($node): bool => $node instanceof Requirement))->toHaveCount(1);
});

it('applies the view policy to single records after the scoped lookup', function (): void {
    asTeacherOf($this->world['teacher'], $this->world['north']->id);

    expect(fn () => app(DataManager::class)->find($this->source, $this->world['subjects']['draft']->id))->toThrow(AuthorizationException::class);
});

it('denies sources with neither a policy nor a requirement', function (): void {
    Report::query()->create(['title' => 'Q1']);
    $this->actingAs($this->world['teacher']);

    expect(fn () => app(DataManager::class)->query(new Source(Report::class)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DataManager::class)->query(Source::table('reports')))->toThrow(AuthorizationException::class)
        ->and(app(DataManager::class)->query(new Source(Report::class, new Authenticated))->pagination->total)->toBe(1);
});

it('authorizes a per-call user with that user\'s own membership', function (): void {
    asTeacherOf($this->world['admin'], $this->world['north']->id);
    $guarded = new Source(Subject::class, Permission::inTenant('subjects.manage'));

    expect(app(DataManager::class)->query($guarded)->isEmpty())->toBeFalse()
        ->and(fn () => app(DataManager::class)->query($guarded, context: QueryContext::for($this->world['teacher'])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DataManager::class)->query($guarded, context: QueryContext::for($this->world['outsider'])))->toThrow(AuthorizationException::class);
});

it('keeps scopes when acting as another user or tenant', function (): void {
    $north = security()->actingAs($this->world['admin'], (string) $this->world['north']->id, fn () => names($this->source, ['per_page' => 50, 'sort' => 'name']));
    $system = security()->actingAs(null, (string) $this->world['south']->id, fn () => app(DataManager::class)->query($this->source, ['per_page' => 50], QueryContext::system())->pagination->total);

    expect($north)->toBe(['Algebra', 'Draft Physics'])
        ->and($system)->toBe(1)
        ->and(security()->context()->tenant)->toBeNull()
        ->and(fn () => security()->actingAs($this->world['teacher'], (string) $this->world['south']->id, fn () => null))->toThrow(TenantAccessException::class);
});
