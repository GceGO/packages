<?php

declare(strict_types=1);

use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\TableManager;
use Gcego\Tables\TablesConfig;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function host(string $definition = 'subjects'): Testable
{
    return Livewire::test(TableHost::class, ['definition' => $definition]);
}

function rowNames(Testable $host): array
{
    preg_match_all('/data-gcego-cell="name">\s*<div[^>]*>\s*<span[^>]*>\s*([^<]+?)\s*</', $host->html(), $matches);

    return $matches[1];
}

it('renders an accessible, semantic table for the documented minimal example', function (): void {
    $html = host('simple')->html();

    expect($html)->toContain('<table')
        ->and($html)->toContain('<caption class="sr-only">')
        ->and($html)->toContain('<th scope="col"')
        ->and($html)->toContain('Subject Name')
        ->and($html)->toContain('Algebra')
        ->and($html)->toContain('aria-label="Pagination"')
        ->and($html)->toContain('data-gcego-boolean="true"')
        ->and($html)->not->toContain('South Physics');
});

it('shows columns with their formatting and never unreadable or hidden values', function (): void {
    $html = host()->html();

    expect($html)->toContain('FCFA')
        ->and($html)->toContain('Core, Lab')
        ->and($html)->toContain('data-gcego-badge="success"')
        ->and($html)->toContain('role="progressbar"')
        ->and($html)->toContain('href="https://gcego.test/algebra"')
        ->and($html)->toContain('rel="noopener noreferrer"')
        ->and($html)->not->toContain('href="javascript:')
        ->and($html)->not->toContain('TOP SECRET')
        ->and($html)->not->toContain('B7')
        ->and($html)->toContain('aria-label="Hidden value"')
        ->and($html)->not->toContain('tok_live_123')
        ->and($html)->not->toContain('data-gcego-column="published_at"')
        ->and($html)->not->toContain('data-gcego-column="secret_notes"');
});

it('escapes record values', function (): void {
    Subject::query()->whereKey($this->world['subjects']['drama']->id)->update(['name' => '<script>alert(1)</script>', 'code' => '"><img src=x onerror=alert(1)>']);

    $html = host()->html();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<img src=x')
        ->and($html)->toContain('&lt;script&gt;');
});

it('searches, resets the page and clears the selection', function (): void {
    $host = host()->set('page', 1)->set('selected', [(string) $this->world['subjects']['algebra']->id])->set('search', 'chem');

    expect(rowNames($host))->toBe(['Chemistry'])
        ->and($host->get('page'))->toBe(1)
        ->and($host->get('selected'))->toBe([]);

    $host->call('resetSearch');

    expect(rowNames($host))->toHaveCount(4);
});

it('filters, shows active filter indicators and resets them', function (): void {
    $host = host()->set('filters.status', 'published');

    expect(rowNames($host))->toBe(['Algebra', 'Chemistry'])
        ->and($host->html())->toContain('data-gcego-indicator="status"');

    $host->call('removeFilter', 'status');

    expect(rowNames($host))->toHaveCount(4);

    $host->set('filters.is_published', '1')->set('filters.status', 'published')->call('resetFilters');

    expect($host->get('filters'))->toBe([]);
});

it('sorts through header controls with aria-sort', function (): void {
    $host = host()->call('sortBy', 'name');

    expect(rowNames($host))->toBe(['Algebra', 'Biology', 'Chemistry', 'Drama'])
        ->and($host->html())->toContain('aria-sort="ascending"');

    $host->call('sortBy', 'name');

    expect(rowNames($host))->toBe(['Drama', 'Chemistry', 'Biology', 'Algebra'])
        ->and($host->html())->toContain('aria-sort="descending"');

    $host->call('sortBy', 'name');

    expect($host->get('sort'))->toBe('');
});

it('shows the page size in effect in the page-size choice, also before one was chosen', function (): void {
    $host = host();

    expect($host->get('perPage'))->toBe(0)
        ->and($host->html())->toContain('data-gcego-per-page="10"');   // the fixture's default page size

    $host->set('perPage', 25);

    expect($host->html())->toContain('data-gcego-per-page="25"');
});

it('sets the page size as a number through the listbox, never through a wire: expression', function (): void {
    // In a wire: directive, Livewire 4 rewrites bare identifiers to "$wire.Number(...)": a server call whose Promise reaches $perPage as an array.
    // The listbox config is JSON inside @js(): quotes arrive as \u0022.
    $html = str_replace('\\u0022', '"', html_entity_decode(host()->html()));

    expect($html)->toContain('"binding":"perPage"')
        ->and($html)->toContain('"cast":"number"')
        ->not->toContain('wire:change="$set(\'perPage\'');
});

it('paginates with page buttons and a page-size choice', function (): void {
    $host = host()->set('perPage', 5);

    expect($host->html())->toContain('Showing 1 to 4 of 4')
        ->and($host->html())->toContain('aria-current="page"');

    for ($i = 0; $i < 8; $i++) {
        $subject = new Subject(['name' => 'Extra '.$i]);
        $subject->institution_id = 1;
        $subject->save();
    }

    $host->call('gotoPage', 3);

    expect($host->html())->toContain('Showing 11 to 12 of 12');

    $host->call('gotoPage', 99);

    expect($host->get('page'))->toBe(3);
});

it('refuses forged filters, sorts and actions', function (string $method, array $arguments): void {
    expect(fn () => host()->{$method}(...$arguments))->toThrow(InvalidTableStateException::class);
})->with([
    'unknown filter' => ['set', ['filters.api_token', 'x']],
    'replacing all filters' => ['set', ['filters', ['institution_id' => 2]]],
    'filter on unreadable column' => ['set', ['filters.secret_notes', 'x']],
    'unsortable column' => ['call', ['sortBy', 'api_token']],
    'unreadable sort' => ['call', ['sortBy', 'secret_notes']],
    'forged row action' => ['call', ['runAction', 'force-delete', '1']],
    'header action as row action' => ['call', ['runAction', 'create', '1']],
    'forged bulk action' => ['call', ['runBulkAction', 'delete']],
    'hiding a required column' => ['call', ['toggleColumn', 'name']],
    'unknown column' => ['call', ['toggleColumn', 'api_token']],
]);

it('locks the definition and layout state', function (string $property, mixed $value): void {
    expect(fn () => host()->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([['definition', 'simple'], ['order', ['secret_notes']], ['hidden', []], ['view', 'x'], ['cursor', 'abc']]);

it('resets tampered state instead of failing', function (): void {
    $host = host()->set('sort', 'api_token')->set('page', 1);

    expect($host->html())->toContain('were not valid and were reset')
        ->and($host->get('sort'))->toBe('');
});

it('shows empty and no-results states', function (): void {
    expect(host()->set('search', 'zzz')->html())->toContain('No matching records')->toContain('Clear search and filters');

    actAs($this->world['south_user'], 2);
    Subject::query()->delete();

    expect(host()->html())->toContain('No records yet');
});

it('shows a safe error state when the query fails', function (): void {
    Schema::drop('subject_tag');

    $html = host()->html();

    expect($html)->toContain('role="alert"')
        ->and($html)->toContain('could not be loaded')
        ->and($html)->not->toContain('SQLSTATE')
        ->and($html)->not->toContain('subject_tag');
});

it('re-authorizes every request', function (): void {
    $host = host();
    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);

    $host->call('refresh')->assertForbidden();
});

it('denies the mount to users the blueprint does not allow', function (): void {
    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);

    host()->assertForbidden();
});

it('selects visible rows, bounded and stable', function (): void {
    $host = host()->call('selectPage');
    $ids = array_map(fn ($subject) => (string) $subject->id, array_values($this->world['subjects']));

    expect($host->get('selected'))->toEqualCanonicalizing($ids)
        ->and($host->html())->toContain('4 rows selected')
        ->and($host->html())->toContain('aria-selected="true"');

    $host->call('clearSelection');

    expect($host->get('selected'))->toBe([]);

    config()->set('gcego-tables.selection.max_records', 2);
    app()->forgetInstance(TablesConfig::class);
    app()->forgetInstance(TableManager::class);

    expect(host()->call('selectPage')->get('selected'))->toHaveCount(2);
});

it('drops malformed selection keys', function (): void {
    expect(host()->set('selected', ['1', '1', '<script>', str_repeat('x', 300), '2'])->get('selected'))->toBe(['1', '2']);
});

it('renders without serializing models into the snapshot', function (): void {
    $snapshot = json_encode(host()->snapshot);

    expect($snapshot)->not->toContain('Algebra')
        ->and($snapshot)->not->toContain('TOP SECRET')
        ->and(strlen((string) $snapshot))->toBeLessThan(2500);
});
