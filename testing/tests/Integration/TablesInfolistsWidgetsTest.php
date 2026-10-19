<?php

declare(strict_types=1);

use Gcego\Tables\Table;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;

it('asserts the documented table columns', function (): void {
    GcegoTest::table('subjects')
        ->assertHasColumn('name')
        ->assertColumnLabel('name', 'Subject Name')
        ->assertColumnSearchable('name')
        ->assertColumnSortable('name')
        ->assertColumnSearchable('code', false)
        ->assertMissingColumn('institution_id')
        ->assertSearchable()
        ->assertDefaultPerPage(25)
        ->assertHasRowAction('publish')
        ->assertHasBulkAction('bulk-delete')
        ->assertHealthy();
});

it('produces the documented failure for a column that is not searchable', function (): void {
    $message = failure(fn () => GcegoTest::table('subjects')->assertColumnSearchable('code'));

    expect($message)->toContain('GCEGO TABLE ASSERTION FAILED')
        ->toContain('Expected table column [code] to be searchable.')
        ->toContain('Table: subjects')
        ->toContain("Expected:\n  searchable = true")
        ->toContain("Actual:\n  searchable = false")
        ->toContain('component = Gcego\Tables\Columns\Text')
        ->toContain('Enable searching for the registered column');
});

it('checks a Table instance without registering it', function (): void {
    $table = Definitions::subjects()->part('table');

    expect($table)->toBeInstanceOf(Table::class);
    GcegoTest::table($table instanceof Table ? $table : new Table)->assertHasColumn('code');
    expect(fn () => GcegoTest::table($table instanceof Table ? $table : new Table)->query())->toThrow(LogicException::class);
});

it('queries the table as the acting user', function (): void {
    $world = world();
    $table = GcegoTest::table('subjects')->actingAs($world['admin'], 1);

    $table->query()->assertKeys([$world['algebra'], $world['draft']])->assertMissing([$world['southern']]);
    $table->query(['search' => 'dra'])->assertKeys([$world['draft']]);
    $table->query(['sorts' => [['column' => 'name', 'direction' => 'desc']]])->assertPluck('name', ['Draft', 'Algebra']);
    $table->query(['per_page' => 10])->assertTotal(2);
});

it('refuses forged table state and unreadable columns', function (): void {
    $world = world();

    GcegoTest::table('subjects')->actingAs($world['editor'], 1)
        ->assertRejectsState(['sorts' => [['column' => 'secret_note', 'direction' => 'asc']]])
        ->assertRejectsState(['sorts' => [['column' => 'institution_id', 'direction' => 'asc']]])
        ->assertRejectsState(['filters' => ['institution_id' => 2]])
        ->assertRejectsState(['page' => -1])
        ->assertColumnHidden('secret_note')
        ->assertColumnReadable('name')
        ->assertExportColumns(['name', 'code']);

    GcegoTest::table('subjects')->actingAs($world['admin'], 1)->assertColumnReadable('secret_note')->assertExportColumns(['name', 'code', 'secret_note']);
});

it('refuses the table to a guest', function (): void {
    GcegoTest::table('subjects')->actingAs(null)->assertForbidden();
});

it('shows infolist values as the acting user sees them', function (): void {
    $world = world();

    GcegoTest::infolist('subjects')->actingAs($world['editor'], 1)->record($world['algebra'])
        ->assertHasEntry('name')
        ->assertEntryLabel('name', 'Subject Name')
        ->assertValue('name', 'Algebra')
        ->assertReadable('code')
        ->assertRedacted('secret_note')
        ->assertRecordNotFound($world['southern']->id)
        ->assertRecordNotFound('../../etc/passwd')
        ->assertHealthy();

    GcegoTest::infolist('subjects')->actingAs($world['admin'], 1)->record($world['algebra'])->assertValue('secret_note', 'algebra-secret');
});

it('never renders a redacted value', function (): void {
    $world = world();

    GcegoTest::infolist('subjects')->actingAs($world['editor'], 1)->record($world['algebra'])->html()
        ->assertSeeText('Algebra')
        ->assertDontSee('algebra-secret');
});

it('forbids a record the policy denies', function (): void {
    $world = world();

    GcegoTest::infolist('subjects')->actingAs($world['student'], 1)->record($world['draft'])->assertForbidden();
});

it('resolves widget data for the acting user', function (): void {
    $world = world();

    GcegoTest::widget('widget:students')->actingAs($world['admin'], 1)
        ->assertAuthorized()
        ->assertValue(42)
        ->assertRefreshEvery(null)
        ->assertHealthy();

    GcegoTest::widget('enrolments')->actingAs($world['admin'], 1)
        ->state(['range' => 'last_7_days'])->assertValue(7)
        ->assertRejectsState(['range' => 'forever'])
        ->assertRejectsState(['range' => 'custom', 'from' => '2026-01-01', 'to' => '2020-01-01']);

    GcegoTest::widget('students')->actingAs(null)->assertForbidden();
});
