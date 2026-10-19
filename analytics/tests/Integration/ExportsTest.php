<?php

declare(strict_types=1);

use Gcego\Analytics\Events\ExportCompleted;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Integration\AnalyticsNotifier;
use Gcego\Analytics\Jobs\GenerateAnalyticsReport;
use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function exportReport(string $format = 'csv', array $filters = [], bool $queue = true): AnalyticsExport
{
    $report = app(AnalyticsDefinitions::class)->report('subject-performance');

    return app(ExportManager::class)->request($report, new ReportRequest($filters), $format, analytics()->context(), queue: $queue);
}

function exportContents(AnalyticsExport $export): string
{
    return (string) Storage::disk($export->disk)->get((string) $export->path);
}

beforeEach(function (): void {
    $this->world = world();
});

it('generates a CSV export in the request, with neutralized formulas', function (): void {
    Subject::query()->whereKey($this->world['subjects']['chemistry']->id)->update(['name' => '=HYPERLINK("http://evil.test")']);
    Event::fake([ExportCompleted::class]);
    actAs($this->world['teacher']);

    $export = exportReport();
    $csv = exportContents($export);

    expect($export->status)->toBe(AnalyticsExport::Completed)->and($export->rows)->toBe(3)->and($export->expires_at?->isFuture())->toBeTrue()
        ->and($export->path)->toContain($export->ulid)->and($export->path)->not->toContain('subject-performance')
        ->and($csv)->toStartWith("\u{FEFF}Subject,Attempts,Students,\"Average score\"")
        ->and($csv)->toContain('Mathematics,4,3,70')
        ->and($csv)->toContain('"\'=HYPERLINK(""http://evil.test"")"')
        ->and($csv)->not->toContain('Distinct emails');
    Event::assertDispatched(ExportCompleted::class);
});

it('exports XLSX through openspout', function (): void {
    actAs($this->world['teacher']);
    $export = exportReport('xlsx');

    expect($export->status)->toBe(AnalyticsExport::Completed)->and(substr(exportContents($export), 0, 2))->toBe('PK');
});

it('queues large exports after the commit and generates them in chunks', function (): void {
    $this->reboot(['gcego-analytics.exports.sync_rows' => 1, 'gcego-analytics.exports.chunk' => 10]);
    $world = world();
    Queue::fake([GenerateAnalyticsReport::class]);
    actAs($world['teacher']);

    $export = exportReport();

    expect($export->status)->toBe(AnalyticsExport::Pending);
    Queue::assertPushedOn('analytics', GenerateAnalyticsReport::class);

    (new GenerateAnalyticsReport($export->id))->handle(app(ExportManager::class));
    $export->refresh();

    expect($export->status)->toBe(AnalyticsExport::Completed)->and(substr_count(exportContents($export), "\n"))->toBe(4)
        ->and($world['teacher']->notifications()->where('data->title', 'gcego-analytics::messages.notifications.ready.title')->exists())->toBeTrue();

    // A second delivery of the same job does nothing.
    (new GenerateAnalyticsReport($export->id))->handle(app(ExportManager::class));
    expect($world['teacher']->notifications()->count())->toBe(1);
});

it('enforces export permission, formats, size and concurrency limits', function (): void {
    actAs($this->world['student1']);
    expect(fn () => exportReport())->toThrow(AnalyticsAuthorizationException::class);

    actAs($this->world['teacher']);
    expect(fn () => exportReport('pdf'))->toThrow(AnalyticsQueryException::class, 'format');

    $this->reboot(['gcego-analytics.exports.max_rows' => 2]);
    $world = world();
    actAs($world['teacher']);
    expect(fn () => exportReport())->toThrow(AnalyticsQueryException::class, 'larger than 2 rows');

    $this->reboot(['gcego-analytics.exports.sync_rows' => 0, 'gcego-analytics.exports.max_concurrent' => 1]);
    $world = world();
    Queue::fake();
    actAs($world['teacher']);
    exportReport();
    expect(fn () => exportReport())->toThrow(AnalyticsQueryException::class, 'too many exports');
});

it('downloads only through a signed link, for the owner in the same tenant, re-authorized', function (): void {
    actAs($this->world['teacher']);
    $export = exportReport();
    $url = AnalyticsNotifier::downloadUrl($export);
    auth()->logout();

    $this->actingAs($this->world['teacher'])->withSession(tenantSession())->get((string) $url)
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Disposition', 'attachment; filename="subject-performance-'.$export->completed_at?->format('Ymd-Hi').'.csv"')
        ->assertDownload();

    $this->actingAs($this->world['teacher'])->withSession(tenantSession())->get(route('gcego.analytics.exports.download', ['export' => $export->ulid]))->assertForbidden();
    app('auth')->forgetGuards();
    $this->actingAs($this->world['admin'])->withSession(tenantSession())->get((string) $url)->assertNotFound();
    app('auth')->forgetGuards();
    $this->actingAs($this->world['south'])->withSession(tenantSession(2))->get((string) $url)->assertNotFound();
    app('auth')->forgetGuards();
    $this->get((string) $url)->assertRedirect('/login');

    $this->travel(25)->hours();
    app('auth')->forgetGuards();
    // The link has expired (403), and so has the export itself, even behind a freshly signed link (404).
    $this->actingAs($this->world['teacher'])->withSession(tenantSession())->get((string) $url)->assertForbidden();
    $fresh = URL::temporarySignedRoute('gcego.analytics.exports.download', now()->addHour(), ['export' => $export->ulid]);
    $this->actingAs($this->world['teacher'])->withSession(tenantSession())->get($fresh)->assertNotFound();
});

it('prunes expired export files', function (): void {
    actAs($this->world['teacher']);
    $export = exportReport();
    $path = (string) $export->path;
    $this->travel(25)->hours();

    $this->artisan('gcego:analytics:prune-exports')->expectsOutputToContain('Expired 1')->assertSuccessful();

    expect($export->refresh()->status)->toBe(AnalyticsExport::Expired)->and(Storage::disk('local')->exists($path))->toBeFalse()->and($path)->not->toBe('');
});
