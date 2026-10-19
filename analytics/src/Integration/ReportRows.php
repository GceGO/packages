<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration;

use Gcego\Analytics\AnalyticsManager;
use Gcego\Data\Source;

/**
 * A report's rows as a gcego/data collection source, for a gcego/tables
 * Table (its sorting, searching and pagination then run in memory on the
 * bounded, already-authorized aggregate rows):
 *
 *     new Table(ReportRows::source('subject-performance'), new Text('subject_id', 'Subject'), new Text('average_score', 'Average'))
 *
 * Rows are read when the table runs, as its viewer, at most queries.max_rows.
 */
final class ReportRows
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function source(string $report, array $filters = []): Source
    {
        return Source::collection(static function () use ($report, $filters): array {
            $handle = app(AnalyticsManager::class)->report($report);
            $rows = [];
            $page = 1;

            do {
                $result = $handle->run(filters: $filters, page: $page, perPage: 200);

                foreach ($result->rows as $index => $row) {
                    $item = ['id' => ($page - 1) * 200 + $index + 1];

                    foreach ($row['cells'] as $name => $cell) {
                        $item[$name] = $cell['value'];
                    }

                    $rows[] = $item;
                }

                $page++;
            } while ($page <= $result->lastPage() && count($rows) < 10000);

            return $rows;
        });
    }
}
