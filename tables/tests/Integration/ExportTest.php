<?php

declare(strict_types=1);

use Gcego\Tables\Export\CsvWriter;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function csv(array $state = [], bool $visibleOnly = true): string
{
    $stream = fopen('php://memory', 'w+b');
    app(CsvWriter::class)->write(tables()->export('subjects', $state, $visibleOnly), $stream);
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('exports the user\'s filtered, sorted view with readable, exportable columns only', function (): void {
    $export = tables()->export('subjects', ['filters' => ['status' => 'published'], 'sorts' => [['column' => 'name', 'direction' => 'desc']]]);
    $rows = iterator_to_array($export->rows(), false);

    expect($export->headings())->toContain('Subject Name')
        ->and($export->columns)->not->toContain('secret_notes')
        ->and($export->columns)->not->toContain('salary_band')
        ->and($export->columns)->not->toContain('published_at')
        ->and(array_column($rows, 0))->toBe(['Chemistry', 'Algebra'])
        ->and(csv())->not->toContain('TOP SECRET')
        ->and(csv())->not->toContain('B7')
        ->and(csv())->not->toContain('tok_live_123')
        ->and(csv())->not->toContain('South Physics');
});

it('exports plain values, not formatted HTML, and neutralizes spreadsheet formulas', function (): void {
    Subject::query()->whereKey($this->world['subjects']['drama']->id)->update(['name' => '=HYPERLINK("http://evil")', 'code' => '@SUM(A1)']);

    $csv = csv();

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain("'@SUM(A1)")
        ->and($csv)->toContain(',25000,')
        ->and($csv)->not->toContain('<')
        ->and(CsvWriter::cell(-5))->toBe('-5')
        ->and(CsvWriter::cell('-5.5'))->toBe('-5.5');
});

it('streams large exports in bounded chunks', function (): void {
    for ($i = 0; $i < 230; $i++) {
        $subject = new Subject(['name' => sprintf('Bulk %03d', $i)]);
        $subject->institution_id = 1;
        $subject->save();
    }

    $queries = queriesDuring(function () use (&$count): void {
        $count = iterator_count(tables()->export('subjects')->rows());
    });

    expect($count)->toBe(234)
        ->and(implode(' ', $queries))->not->toContain('count(*)')
        ->and(count($queries))->toBeLessThanOrEqual(12)
        ->and(iterator_count(tables()->export('subjects')->rows(limit: 10)))->toBe(10);
});

it('re-authorizes when the export is built', function (): void {
    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);

    expect(fn () => tables()->export('subjects'))->toThrow(AuthorizationException::class);
});

it('includes hidden-but-readable columns only when asked', function (): void {
    expect(tables()->export('subjects', visibleOnly: false)->columns)->toContain('published_at')
        ->and(tables()->export('subjects', visibleOnly: false)->columns)->not->toContain('secret_notes');
});
