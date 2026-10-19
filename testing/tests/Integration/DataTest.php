<?php

declare(strict_types=1);

use Gcego\Data\Exceptions\InvalidFilterException;
use Gcego\Data\Exceptions\InvalidSortException;
use Gcego\Data\Search\Search;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Sorting\Sortable;
use Gcego\Data\Source;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

function subjectsSource(): Source
{
    return new Source(Subject::class, new Search('name'), new Sort('name'), new Sortable('name', 'code'));
}

it('queries a source as the acting user, with tenant scopes', function (): void {
    $world = world();

    GcegoTest::data(subjectsSource())
        ->actingAs($world['admin'], 1)
        ->query()
        ->assertKeys([$world['algebra'], $world['draft']])
        ->assertTotal(2)
        ->assertPluck('name', ['Algebra', 'Draft'])
        ->assertMissing([$world['southern']]);
});

it('searches, sorts and paginates through the query input', function (): void {
    $world = world();
    $data = GcegoTest::data(subjectsSource())->actingAs($world['admin'], 1);

    $data->query(['search' => 'alg'])->assertKeys([$world['algebra']]);
    $data->query(['sort' => '-name'])->assertPluck('name', ['Draft', 'Algebra']);
    $data->query(['per_page' => 1, 'page' => 2])->assertCount(1)->assertPage(2, 1)->assertHasMorePages(false);
});

it('refuses forged filters and sorts', function (): void {
    $world = world();

    GcegoTest::data(subjectsSource())->actingAs($world['admin'], 1)
        ->assertRejects(['filter' => ['institution_id' => 2]], InvalidFilterException::class)
        ->assertRejects(['sort' => 'secret_note'], InvalidSortException::class);
});

it('finds records in scope only', function (): void {
    $world = world();

    GcegoTest::data(subjectsSource())->actingAs($world['admin'], 1)
        ->assertFinds($world['algebra']->id)
        ->assertNotFound($world['southern']->id)
        ->assertNotFound('1 OR 1=1')
        ->assertHealthy();
});

it('denies guests and fails closed without a tenant', function (): void {
    world();

    GcegoTest::data(subjectsSource())->actingAs(null)->assertDenied();
    GcegoTest::data(subjectsSource())->asSystem()->query()->assertEmpty();
});
