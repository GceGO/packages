<?php

declare(strict_types=1);

namespace Gcego\Analytics\Http;

use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Exports\ExportManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET analytics/exports/{export}: a signed, expiring link (route
 * "gcego.analytics.exports.download"), still re-authorized when followed:
 * signed in, same tenant, owner or recipient, report access, not expired.
 */
final class ExportDownloadController
{
    public function __invoke(string $export, ExportManager $exports, AnalyticsManager $analytics): StreamedResponse
    {
        return $exports->download($export, $analytics->context());
    }
}
