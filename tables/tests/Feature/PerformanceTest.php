<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Source;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\Table;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
    $now = now()->toDateTimeString();
    $rows = [];

    for ($i = 0; $i < 5000; $i++) {
        $rows[] = ['name' => sprintf('Subject %05d', $i), 'code' => sprintf('S%05d', $i), 'status' => $i % 2 === 0 ? 'published' : 'draft', 'fee' => $i * 10, 'credits' => $i % 7, 'is_published' => $i % 2 === 0, 'category_id' => $this->world['categories']['science']->id, 'institution_id' => $i % 10 === 0 ? 2 : 1, 'created_at' => $now, 'updated_at' => $now];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('subjects')->insert($chunk);
    }
});

it('renders a page of a 5,000-row table with a constant number of queries', function (): void {
    $started = hrtime(true);
    $queries = queriesDuring(function () use (&$host): void {
        $host = Livewire::test(TableHost::class, ['definition' => 'subjects']);
    });
    $mountMs = (hrtime(true) - $started) / 1e6;

    $started = hrtime(true);
    $searchQueries = queriesDuring(fn () => $host->set('search', 'Subject 04'));
    $searchMs = (hrtime(true) - $started) / 1e6;

    $started = hrtime(true);
    $sortQueries = queriesDuring(function () use ($host): void {
        for ($i = 0; $i < 5; $i++) {
            $host->call('sortBy', 'fee');
        }
    });
    $sortMs = (hrtime(true) - $started) / 1e6;

    $filterQueries = queriesDuring(fn () => $host->set('filters.status', 'published')->set('filters.is_published', '1'));
    $snapshot = strlen((string) json_encode($host->snapshot));
    $sql = implode("\n", $queries);

    fwrite(STDERR, sprintf("\n[tables perf] 5,000 rows (+500 other tenant): mount %.1f ms / %d queries; search %.1f ms / %d queries; 5 sorts %.1f ms / %d queries; 2 filters %d queries; snapshot %d bytes\n", $mountMs, count($queries), $searchMs, count($searchQueries), $sortMs, count($sortQueries), count($filterQueries), $snapshot));

    expect(count($queries))->toBeLessThanOrEqual(12)
        ->and($sql)->toContain('limit 10')
        ->and(array_filter($queries, static fn (string $query): bool => str_starts_with($query, 'select * from "subjects"') && ! str_contains($query, ' limit ')))->toBe([])
        ->and(count($searchQueries))->toBeLessThanOrEqual(8)
        ->and(count($sortQueries))->toBeLessThanOrEqual(40)
        ->and($snapshot)->toBeLessThan(3000)
        ->and(count($host->get('selected')))->toBe(0);
});

it('keeps selections bounded no matter how many keys the browser sends', function (): void {
    $keys = array_map(strval(...), range(1, 20000));
    $host = Livewire::test(TableHost::class, ['definition' => 'subjects'])->set('selected', $keys);

    expect($host->get('selected'))->toHaveCount(500);
});

it('uses simple pagination without COUNT when configured', function (): void {
    app(Registry::class)->register(new Blueprint('fast', new Ability('viewAny', Subject::class), new Source(Subject::class), new Table(
        new Text('name'),
        new Pagination(25, mode: PaginationMode::Simple),
    )));

    $queries = queriesDuring(fn () => Livewire::test(TableHost::class, ['definition' => 'fast'])->call('nextPage'));

    expect(implode(' ', $queries))->not->toContain('count(*)')
        ->and(implode(' ', $queries))->toContain('limit 26');
});

it('escapes echoed search terms and filter indicators', function (): void {
    $html = Livewire::test(TableHost::class, ['definition' => 'subjects'])->set('search', '<img src=x onerror=alert(1)>')->html();

    expect($html)->not->toContain('<img src=x')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});
