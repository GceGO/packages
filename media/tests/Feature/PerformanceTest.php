<?php

declare(strict_types=1);

use Gcego\Media\Definitions\Variant;
use Gcego\Media\Library\MediaLibraryQuery;
use Gcego\Media\Library\MediaQuery;
use Gcego\Media\Livewire\MediaLibrary;
use Gcego\Media\MediaCollection;
use Gcego\Media\Models\MediaRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('pages a large library with bounded, indexed queries', function (): void {
    $world = world();
    actAs($world['admin']);
    $rows = [];

    foreach (range(1, 5000) as $number) {
        $rows[] = ['ulid' => strtolower((string) Str::ulid()), 'tenant' => '1', 'uploader_type' => $world['admin']->getMorphClass(), 'uploader_id' => $world['admin']->id, 'collection' => 'library', 'disk' => 'local', 'path' => 'gcego-media/shared/library/2026/10/x/'.$number.'.pdf', 'original_name' => 'file-'.$number.'.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'size' => $number * 100, 'checksum' => str_repeat('a', 64), 'visibility' => 'private', 'status' => 'ready', 'scan_status' => 'unscanned', 'created_at' => now()->subMinutes($number), 'updated_at' => now()];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('gcego_media')->insert($chunk);
    }

    $start = hrtime(true);
    $queries = queriesDuring(static function () use (&$page): void {
        $page = app(MediaLibraryQuery::class)->page(MediaQuery::fromInput('file-49'), 1, 24);
    });
    $milliseconds = (hrtime(true) - $start) / 1e6;
    $component = Livewire::test(MediaLibrary::class);
    $snapshot = strlen((string) json_encode($component->snapshot));

    recordPerformance(['media.library_5000.search_queries' => count($queries), 'media.library_5000.search_ms' => round($milliseconds, 1), 'media.library.snapshot_bytes' => $snapshot]);

    expect(count($queries))->toBe(2)
        ->and($page->total())->toBe(111)
        ->and(count($page->items()))->toBe(24)
        ->and($snapshot)->toBeLessThan(2500);
});

it('measures upload, processing and variant generation overhead', function (): void {
    $world = world();
    actAs($world['admin']);
    $files = array_map(static fn (int $i) => fixtureFile('jpg', 'photo-'.$i.'.jpg', 2000, 1500), range(1, 10));
    $start = hrtime(true);
    $results = media()->uploadMany($files, 'library');
    $milliseconds = (hrtime(true) - $start) / 1e6;

    recordPerformance(['media.upload_10x_3mp_jpeg_sync_processing.ms' => round($milliseconds, 1), 'media.upload_10x_3mp_jpeg_sync_processing.per_file_ms' => round($milliseconds / 10, 1)]);

    expect(array_filter($results, static fn ($result): bool => $result->successful()))->toHaveCount(10)
        ->and(MediaRecord::query()->whereNotNull('variants')->count())->toBe(10);
});

it('never touches storage or the database while definitions are built', function (): void {
    $queries = queriesDuring(static fn () => (new MediaCollection('x'))->image()->maxFiles(3)->variants(Variant::make('t', 100)));

    expect($queries)->toBe([])
        ->and(app('filesystem')->disk('local')->allFiles())->toBe([]);
});
