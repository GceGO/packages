# Tables, infolists, widgets and data sources

## Tables

`GcegoTest::table('subjects')` takes a registered definition. Queries go through the same path `TableHost` uses:

1. Authorize the table.
2. Decide column access.
3. Normalize the state strictly.
4. Run `TableManager::query()`.

A `Table` instance can be passed instead for the definition assertions alone.

<!-- example:tables -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::table('subjects')
    ->assertHasColumn('name')
    ->assertColumnLabel('name', 'Subject Name')
    ->assertColumnSearchable('name')
    ->assertColumnSortable('name')
    ->assertHasBulkAction('bulk-delete');

GcegoTest::table('subjects')->actingAs($editor, 1)
    ->assertColumnHidden('secret_note')
    ->assertRejectsState(['sorts' => [['column' => 'secret_note', 'direction' => 'asc']]])
    ->assertExportColumns(['name', 'code'])
    ->query(['search' => 'alg'])
    ->assertKeys([$algebra])
    ->assertMissing([$southern]);
```

- **Queries**: `query($state)` takes untrusted table state (`search`, `filters`, `sorts`, `page`, `per_page`, `cursor`) and returns a result testable:
  - `assertKeys()` (exact, in order), `assertContains()`, `assertMissing()`;
  - `assertCount()` (this page), `assertTotal()`, `assertPluck($field, $values)`;
  - `assertPage()`, `assertHasMorePages()`, `assertEmpty()`.
- **Forged state**: `assertRejectsState()` expects `InvalidTableStateException`, raised before any query. This covers a forged sort column, an undeclared filter, a bad page and similar input.
- **Column access**: `assertColumnHidden()`, `assertColumnMasked()` and `assertColumnReadable()` check the acting user's column access.

## Data sources

<!-- example:data -->
```php
use Gcego\Data\Search\Search;
use Gcego\Data\Source;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

GcegoTest::data(new Source(Subject::class, new Search('name')))
    ->actingAs($admin, 1)
    ->assertRejects(['filter' => ['institution_id' => 2]])
    ->assertNotFound($southern->id)
    ->query(['search' => 'alg'])
    ->assertKeys([$algebra]);
```

- **Acting user**: queries run as the acting user, with policies and tenant scopes applied.
- **System context**: `asSystem()` runs with `QueryContext::system()`. Authorization is skipped, but scopes still apply, so without a tenant a tenant-scoped source returns nothing.

## Infolists

<!-- example:infolists -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::infolist('subjects')->actingAs($editor, 1)->record($algebra)
    ->assertValue('name', 'Algebra')
    ->assertRedacted('secret_note')
    ->assertRecordNotFound($southern->id)
    ->html()->assertDontSee('algebra-secret');
```

- **Values**: `assertValue($path, $text)` is the formatted text the user sees.
- **Access levels**:
  - `assertRedacted()`: the user sees a mask and the value is never read.
  - `assertEntryHidden()`: nothing is rendered.
- **Records**: `assertRecordNotFound()` expects 404 for out-of-scope, forged or missing keys. `assertForbidden()` expects 403 when the policy denies.

## Widgets

<!-- example:widgets -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::widget('students')->actingAs($admin, 1)->assertAuthorized()->assertValue(42);
GcegoTest::widget('students')->actingAs(null)->assertForbidden();
```

- **Data**: `data()` returns the `WidgetDataResult` for the acting user and tenant, after authorization.
- **Data assertions**: `assertPrevious()`, `assertLabels()`, `assertDataset($index, $label, $data)` and `assertItemCount()`.
- **State and refresh**: `assertRejectsState()` covers forged ranges and dates; `assertRefreshEvery()` checks the polling interval.
