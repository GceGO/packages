<?php

declare(strict_types=1);

use Gcego\Tables\Contracts\PreferenceStore;
use Gcego\Tables\Contracts\SavedViewRepository;
use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\State\TableState;
use Gcego\Tables\Support\TableOwner;
use Gcego\Tables\TableManager;
use Gcego\Tables\TablesConfig;
use Gcego\Tables\Tests\Fixtures\Models\Membership;
use Gcego\Tables\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function table(string $definition = 'subjects'): Testable
{
    return Livewire::test(TableHost::class, ['definition' => $definition]);
}

function ownerOf(User $user, int $tenant = 1): TableOwner
{
    return new TableOwner('web', (string) $user->id, 'institution:'.$tenant);
}

it('hides, shows and reorders columns and remembers them per user', function (): void {
    $host = table()->call('toggleColumn', 'code')->call('moveColumn', 'credits', -1)->call('toggleColumn', 'published_at');

    expect($host->get('hidden'))->toBe(['code'])
        ->and(array_slice($host->get('order'), 0, 5))->toBe(['name', 'code', 'category.name', 'credits', 'tags.label'])
        ->and($host->html())->not->toContain('data-gcego-column="code"')
        ->and($host->html())->toContain('data-gcego-column="published_at"');

    $again = table();

    expect($again->get('hidden'))->toBe(['code'])
        ->and($again->get('order')[3])->toBe('credits');

    actAs($this->world['admin']);

    expect(table()->get('hidden'))->toBe(['published_at']);
});

it('keeps preferences per table and per tenant', function (): void {
    table()->call('toggleColumn', 'code');

    expect(table('simple')->get('hidden'))->toBe([]);

    Membership::query()->create(['user_id' => $this->world['editor']->id, 'institution_id' => 2]);
    actAs($this->world['editor'], 2);

    expect(table()->get('hidden'))->toBe(['published_at']);
});

it('resets columns and density to the definition', function (): void {
    $host = table()->call('toggleColumn', 'code')->call('setDensity', 'compact')->set('perPage', 25);

    expect(app(PreferenceStore::class)->get(ownerOf($this->world['editor']), 'blueprint:subjects#table')['state'])->toMatchArray(['hidden' => ['published_at', 'code'], 'density' => 'compact', 'per_page' => 25]);

    $host->call('resetColumns');

    expect($host->get('hidden'))->toBe(['published_at'])
        ->and($host->get('density'))->toBe('comfortable')
        ->and(app(PreferenceStore::class)->get(ownerOf($this->world['editor']), 'blueprint:subjects#table'))->toBeNull();
});

it('never lets stored preferences expose unreadable columns or crash on tampered rows', function (): void {
    app(PreferenceStore::class)->put(ownerOf($this->world['editor']), 'blueprint:subjects#table', ['order' => ['secret_notes', 'name', 'unknown'], 'hidden' => ['name', 'code'], 'per_page' => 9999], TableState::Version);
    $host = table();

    expect($host->get('order'))->not->toContain('secret_notes')
        ->and($host->get('order'))->not->toContain('unknown')
        // "published_at" is hidden by default and absent from the stored layout, so it stays hidden.
        ->and($host->get('hidden'))->toBe(['code', 'published_at'])
        ->and($host->html())->not->toContain('TOP SECRET');

    DB::table('gcego_table_preferences')->update(['state' => '{not json']);

    expect(table()->get('hidden'))->toBe(['published_at']);
});

it('saves, loads, renames, deletes and defaults views for their owner only', function (): void {
    $host = table()->set('search', 'bio')->set('filters.is_published', '0')->call('sortBy', 'name')->call('toggleColumn', 'code')->call('saveView', 'Drafts');
    $views = app(SavedViewRepository::class)->all(ownerOf($this->world['editor']), 'blueprint:subjects#table');

    expect($views)->toHaveCount(1)
        ->and($views[0]->name)->toBe('Drafts')
        ->and($views[0]->state)->toMatchArray(['search' => 'bio', 'filters' => ['is_published' => false], 'sorts' => [['column' => 'name', 'direction' => 'asc']], 'hidden' => ['published_at', 'code']])
        ->and($host->html())->toContain('View “Drafts” saved.');

    $fresh = table()->call('loadView', $views[0]->id);

    expect($fresh->get('search'))->toBe('bio')
        ->and($fresh->get('sort'))->toBe('name')
        ->and($fresh->get('view'))->toBe($views[0]->id);

    $fresh->call('renameView', $views[0]->id, 'Draft subjects')->call('setDefaultView', $views[0]->id);

    expect(table()->get('search'))->toBe('bio');

    actAs($this->world['admin']);

    expect(table()->get('search'))->toBe('')
        ->and(table()->call('loadView', $views[0]->id)->get('search'))->toBe('')
        ->and(table()->call('deleteView', $views[0]->id)->get('view'))->toBeNull()
        ->and(app(SavedViewRepository::class)->all(ownerOf($this->world['editor']), 'blueprint:subjects#table'))->toHaveCount(1);

    actAs($this->world['editor']);
    table()->call('deleteView', $views[0]->id);

    expect(app(SavedViewRepository::class)->all(ownerOf($this->world['editor']), 'blueprint:subjects#table'))->toBe([]);
});

it('updates a view saved again under the same name and limits views per table', function (): void {
    table()->set('search', 'a')->call('saveView', 'Mine');
    table()->set('search', 'b')->call('saveView', 'Mine');
    $views = app(SavedViewRepository::class)->all(ownerOf($this->world['editor']), 'blueprint:subjects#table');

    expect($views)->toHaveCount(1)
        ->and($views[0]->state['search'])->toBe('b');

    config()->set('gcego-tables.saved_views.max_per_table', 1);
    app()->forgetInstance(TablesConfig::class);
    app()->forgetInstance(SavedViewRepository::class);
    actAs($this->world['editor']);

    expect(fn () => table()->call('saveView', 'Another'))->toThrow(InvalidTableStateException::class);
});

it('reapplies current permissions and tenant when a view is restored', function (): void {
    Gate::define('subjects.secrets', fn ($user): bool => true);
    actAs($this->world['editor']);
    table()->set('search', 'TOP')->call('sortBy', 'secret_notes')->call('saveView', 'Secrets');
    $id = app(SavedViewRepository::class)->all(ownerOf($this->world['editor']), 'blueprint:subjects#table')[0]->id;

    Gate::define('subjects.secrets', fn ($user): bool => false);
    actAs($this->world['editor']);
    $host = table()->call('loadView', $id);

    expect($host->get('sort'))->toBe('')
        ->and($host->get('order'))->not->toContain('secret_notes')
        ->and($host->html())->toContain('no longer apply')
        ->and($host->html())->not->toContain('TOP SECRET');

    Membership::query()->create(['user_id' => $this->world['editor']->id, 'institution_id' => 2]);
    actAs($this->world['editor'], 2);

    expect(table()->call('loadView', $id)->html())->toContain('That view is no longer available.');
});

it('refuses views saved by an incompatible state version and invalid names', function (): void {
    $view = app(SavedViewRepository::class)->create(ownerOf($this->world['editor']), 'blueprint:subjects#table', 'Old', ['search' => 'x'], TableState::Version + 1);

    expect(table()->call('loadView', $view->id)->get('search'))->toBe('')
        ->and(fn () => table()->call('saveView', str_repeat('x', 61)))->toThrow(InvalidTableStateException::class)
        ->and(fn () => table()->call('saveView', "bad\x01name"))->toThrow(InvalidTableStateException::class)
        ->and(table()->call('loadView', 'not-a-ulid')->html())->toContain('no longer available');
});

it('forgets everything stored for a deleted user', function (): void {
    table()->call('toggleColumn', 'code')->call('saveView', 'Mine');

    expect(app(PreferenceStore::class)->forgetOwner('web', (string) $this->world['editor']->id))->toBe(1)
        ->and(app(SavedViewRepository::class)->forgetOwner('web', (string) $this->world['editor']->id))->toBe(1);
});

it('keeps the table working when preference and view storage is unavailable', function (): void {
    Schema::drop('gcego_table_views');
    Schema::drop('gcego_table_preferences');

    $host = table()->call('toggleColumn', 'code');

    expect($host->html())->toContain('Algebra')
        ->and($host->get('hidden'))->toContain('code');
});

it('starts columns added after a layout was saved with their declared default', function (): void {
    $tables = app(TableManager::class);
    $resolved = $tables->resolve('subjects');
    $normalizer = $tables->normalizer($resolved->schema, $tables->access($resolved->schema), strict: false, model: $tables->model($resolved));
    $saved = array_values(array_diff($resolved->schema->columnNames(), ['published_at', 'secret_notes', 'salary_band']));

    // Saved before "published_at" (hidden by default) existed: it stays hidden.
    expect($normalizer->normalize(['order' => $saved, 'hidden' => []])->hidden)->toBe(['published_at'])
        // Saved after the user chose to show it: their choice wins.
        ->and($normalizer->normalize(['order' => [...$saved, 'published_at'], 'hidden' => []])->hidden)->toBe([]);
});
