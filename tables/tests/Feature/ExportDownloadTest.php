<?php

declare(strict_types=1);

use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\TableManager;
use Gcego\Tables\TablesConfig;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
    Carbon::setTestNow('2026-10-18 09:30:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * The CSV a Livewire export call streamed to the browser.
 */
function downloaded(Testable $host): string
{
    $download = $host->effects['download'] ?? null;

    expect($download)->toBeArray();

    return (string) base64_decode((string) $download['content'], true);
}

it('offers Export CSV only on tables that opt in', function (): void {
    expect(Livewire::test(TableHost::class, ['definition' => 'subjects'])->html())->toContain('wire:click="export"')
        ->and(Livewire::test(TableHost::class, ['definition' => 'simple'])->html())->not->toContain('wire:click="export"');
});

it('downloads the rows the user is looking at, as CSV', function (): void {
    $host = Livewire::test(TableHost::class, ['definition' => 'subjects'])
        ->set('search', 'Algebra')
        ->call('export');

    $csv = downloaded($host);
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', ltrim($csv, "\u{FEFF}")))));

    expect($host->effects['download']['name'])->toBe('subjects-2026-10-18-093000.csv')
        ->and($csv)->toStartWith("\u{FEFF}")
        ->and($lines[0])->toContain('Subject Name')
        ->and($csv)->toContain('Algebra')
        ->and($csv)->not->toContain('South Physics')
        ->and(count($lines))->toBe(2);
});

it('leaves out hidden, unreadable and masked columns', function (): void {
    $csv = downloaded(Livewire::test(TableHost::class, ['definition' => 'subjects'])->call('toggleColumn', 'code')->call('export'));
    $heading = strtok(ltrim($csv, "\u{FEFF}"), "\n");

    expect($heading)->not->toContain('Code')
        ->and($heading)->not->toContain('Secret notes')
        ->and($heading)->not->toContain('Band')
        ->and($csv)->not->toContain('TOP SECRET');
});

it('caps the number of rows', function (): void {
    $all = downloaded(Livewire::test(TableHost::class, ['definition' => 'subjects'])->call('export'));

    expect(count(array_filter(explode("\n", trim(ltrim($all, "\u{FEFF}"))))))->toBeGreaterThan(2);

    config()->set('gcego-tables.export.max_rows', 1);
    app()->forgetInstance(TablesConfig::class);
    app()->forgetInstance(TableManager::class);

    $csv = downloaded(Livewire::test(TableHost::class, ['definition' => 'subjects'])->call('export'));

    expect(count(array_filter(explode("\n", trim(ltrim($csv, "\u{FEFF}"))))))->toBe(2);
});

it('refuses to export a table that does not offer it', function (): void {
    Livewire::test(TableHost::class, ['definition' => 'simple'])->call('export');
})->throws(InvalidTableStateException::class);
