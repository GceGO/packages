<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration;

use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Reports\Report;
use Gcego\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * gcego/notifications messages about exports (category "reports",
 * database channel, the export's tenant, idempotent): ready (with a
 * signed, expiring download link that is still authorized when followed),
 * failed, and scheduled deliveries. No report data or files are attached.
 */
final readonly class AnalyticsNotifier
{
    public const string Category = 'reports';

    public function exportReady(AnalyticsExport $export, Model $owner, Report $report): void
    {
        $this->send($owner, $export, $report, 'ready', 'export.'.$export->ulid.'.ready');
    }

    public function exportFailed(AnalyticsExport $export, Model $owner, Report $report): void
    {
        $this->send($owner, $export, $report, 'failed', 'export.'.$export->ulid.'.failed');
    }

    public function scheduled(AnalyticsExport $export, Model $recipient, Report $report): void
    {
        $this->send($recipient, $export, $report, 'scheduled', 'export.'.$export->ulid.'.scheduled.'.substr(hash('sha256', $recipient->getMorphClass().'#'.json_encode($recipient->getKey())), 0, 16));
    }

    public static function downloadUrl(AnalyticsExport $export): ?string
    {
        if ($export->expires_at === null) {
            return null;
        }

        try {
            return URL::temporarySignedRoute('gcego.analytics.exports.download', $export->expires_at, ['export' => $export->ulid]);
        } catch (Throwable) {
            return null;
        }
    }

    private function send(Model $recipient, AnalyticsExport $export, Report $report, string $message, string $key): void
    {
        if (! class_exists(Notification::class) || ! method_exists($recipient, 'routeNotificationFor')) {
            return;
        }

        try {
            $builder = Notification::make()
                ->title('gcego-analytics::messages.notifications.'.$message.'.title')
                ->body('gcego-analytics::messages.notifications.'.$message.'.body')
                ->translate(['report' => $report->title(), 'expires' => $export->expires_at?->toDayDateTimeString() ?? ''])
                ->category(self::Category)
                ->database()
                ->idempotencyKey('analytics.'.$key);
            $builder = $message === 'failed' ? $builder->error() : $builder->success();
            $url = $message === 'failed' ? null : self::downloadUrl($export);

            if ($url !== null) {
                $builder = $builder->action((string) __('gcego-analytics::messages.download'), $url, true);
            }

            $builder = $export->tenant === null ? $builder->global() : $builder->tenant($export->tenant);
            $builder->sendTo($recipient);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
