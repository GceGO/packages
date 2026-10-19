<?php

declare(strict_types=1);

use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Gcego\Tables\Tests\Fixtures\Policies\Permissive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('lists the tenant\'s rows with the default sort and database pagination', function (): void {
    $queries = queriesDuring(fn () => names());

    expect(names())->toBe(['Algebra', 'Biology', 'Chemistry', 'Drama'])
        ->and(implode(' ', $queries))->toContain('limit 10')
        ->and(implode(' ', $queries))->toContain('"institution_id"');
});

it('searches only searchable, readable columns, including relation paths', function (): void {
    expect(names(['search' => 'bio']))->toBe(['Biology'])
        ->and(names(['search' => 'MTH']))->toBe(['Algebra'])
        ->and(names(['search' => 'arts']))->toBe(['Chemistry', 'Drama'])
        ->and(names(['search' => '   ']))->toBe(['Algebra', 'Biology', 'Chemistry', 'Drama'])
        ->and(names(['search' => 'TOP SECRET']))->toBe([])
        ->and(names(['search' => 'South']))->toBe([]);
});

it('treats search input as data, never SQL', function (string $term): void {
    expect(names(['search' => $term]))->toBe([])
        ->and(Subject::query()->withoutGlobalScopes()->count())->toBe(5);
})->with(["' OR 1=1 --", '%', '_', '"; DROP TABLE subjects; --', 'Algebra\' UNION SELECT api_token FROM subjects --']);

it('sorts by permitted columns only', function (): void {
    expect(names(['sorts' => [['column' => 'name', 'direction' => 'desc']]]))->toBe(['Drama', 'Chemistry', 'Biology', 'Algebra'])
        ->and(names(['sorts' => [['column' => 'fee', 'direction' => 'asc']]]))->toBe(['Drama', 'Algebra', 'Chemistry', 'Biology'])
        ->and(fn () => state(['sorts' => [['column' => 'api_token', 'direction' => 'asc']]]))->toThrow(InvalidTableStateException::class)
        ->and(fn () => state(['sorts' => [['column' => 'name; drop table', 'direction' => 'asc']]]))->toThrow(InvalidTableStateException::class)
        ->and(fn () => state(['sorts' => [['column' => 'secret_notes', 'direction' => 'asc']]]))->toThrow(InvalidTableStateException::class)
        ->and(fn () => state(['sorts' => [['column' => 'name', 'direction' => 'sideways']]]))->toThrow(InvalidTableStateException::class);
});

it('keeps one sort unless the table allows several', function (): void {
    expect(state(['sorts' => [['column' => 'name', 'direction' => 'asc'], ['column' => 'code', 'direction' => 'desc']]])->sorts)->toBe([['column' => 'name', 'direction' => 'asc']]);
});

it('applies select, boolean, date, date range and custom filters through gcego/data', function (): void {
    $science = (string) $this->world['categories']['science']->id;

    expect(names(['filters' => ['status' => 'published']]))->toBe(['Algebra', 'Chemistry'])
        ->and(names(['filters' => ['is_published' => '0']]))->toBe(['Biology', 'Drama'])
        ->and(names(['filters' => ['starts_on' => ['from' => '2026-02-01', 'to' => '2026-03-01']]]))->toBe(['Biology', 'Chemistry'])
        ->and(names(['filters' => ['starts_on' => ['from' => '2026-02-01']]]))->toBe(['Biology', 'Chemistry'])
        ->and(names(['filters' => ['published_at' => '2026-03-01']]))->toBe(['Chemistry'])
        ->and(names(['filters' => ['expensive' => '1']]))->toBe(['Biology', 'Chemistry'])
        ->and(names(['filters' => ['category' => $science]]))->toBe(['Algebra', 'Biology'])
        ->and(names(['filters' => ['status' => 'published', 'is_published' => '1', 'category' => $science]]))->toBe(['Algebra']);
});

it('offers relationship filter options through gcego/data, scoped to the tenant', function (): void {
    $filter = resolved()->schema->filter('category');

    expect($filter->options(Subject::class))->toBe([(string) $this->world['categories']['arts']->id => 'Arts', (string) $this->world['categories']['science']->id => 'Science'])
        ->and($filter->options())->toBe([]);

    actAs($this->world['south_user'], 2);

    expect(resolved()->schema->filter('category')->options(Subject::class))->toBe([(string) $this->world['categories']['south']->id => 'South only']);
});

it('compares date-time filters by whole days in the display time zone', function (): void {
    config()->set('gcego-tables.dates.timezone', 'Africa/Douala');
    app()->forgetInstance(TablesConfig::class);

    // Chemistry was published at 2026-03-01 23:30 UTC = 2026-03-02 00:30 in Douala.
    expect(names(['filters' => ['published_at' => '2026-03-02']]))->toBe(['Chemistry'])
        ->and(names(['filters' => ['published_at' => '2026-03-01']]))->toBe([]);
});

it('refuses unknown filters and invalid filter values', function (array $filters): void {
    expect(fn () => state(['filters' => $filters]))->toThrow(InvalidTableStateException::class);
})->with([
    'unknown filter' => [['api_token' => 'x']],
    'not an option' => [['status' => 'deleted']],
    'array injection' => [['status' => ['published', ['nested']]]],
    'bad date' => [['starts_on' => ['from' => '2026-02-30']]],
    'reversed range' => [['starts_on' => ['from' => '2026-03-01', 'to' => '2026-01-01']]],
    'bad boolean' => [['is_published' => 'maybe']],
    'filter on an unreadable column' => [['secret_notes' => 'x']],
    'another tenant\'s category' => [['category' => '3']],
]);

it('paginates with bounded page sizes', function (): void {
    expect(names(['per_page' => 5, 'page' => 1]))->toHaveCount(4)
        ->and(names(['per_page' => 5, 'page' => 2]))->toBe([])
        ->and(fn () => state(['per_page' => 1000]))->toThrow(InvalidTableStateException::class)
        ->and(fn () => state(['per_page' => 7]))->toThrow(InvalidTableStateException::class)
        ->and(fn () => state(['page' => -1]))->toThrow(InvalidTableStateException::class)
        ->and(state()->perPage)->toBe(10);
});

it('eager-loads relation columns instead of querying per row', function (): void {
    $table = resolved();
    $access = tables()->access($table->schema);
    $queries = queriesDuring(function () use ($table, $access): void {
        foreach (tables()->query($table, $access, state())->items() as $record) {
            ValueResolver::get($record, 'category.name');
            ValueResolver::get($record, 'tags.label');
        }
    });

    expect($queries)->toHaveCount(4); // count, page, categories, tags
});

it('removes unreadable columns from search, sort and display and masks masked ones', function (): void {
    $table = resolved();
    $access = tables()->access($table->schema);

    expect($access->canRead('secret_notes'))->toBeFalse()
        ->and($access->isMasked('salary_band'))->toBeTrue()
        ->and(state()->order)->not->toContain('secret_notes')
        ->and(state()->order)->toContain('salary_band');

    Gate::define('subjects.secrets', fn ($user): bool => $user->name === 'Editor');
    actAs($this->world['editor']);

    expect(names(['search' => 'TOP SECRET']))->toBe(['Algebra'])
        ->and(tables()->access(resolved()->schema)->canRead('secret_notes'))->toBeTrue();
});

it('denies users the blueprint does not allow', function (): void {
    Gate::policy(Subject::class, Permissive::class);
    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);
    actAs($this->world['editor']);

    expect(fn () => tables()->authorize(resolved()))->toThrow(AuthorizationException::class);
});

it('never resolves tables by class or unknown identities', function (string $definition): void {
    expect(fn () => tables()->resolve($definition))->toThrow(Exception::class);
})->with([Subject::class, 'blueprint:nope', '../../etc/passwd', 'subjects/../simple']);

it('isolates tenants', function (): void {
    actAs($this->world['south_user'], 2);

    expect(names())->toBe(['South Physics'])
        ->and(names(['search' => 'Algebra']))->toBe([]);
});
