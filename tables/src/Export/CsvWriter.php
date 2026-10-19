<?php

declare(strict_types=1);

namespace Gcego\Tables\Export;

use Gcego\Tables\Contracts\ExportWriter;
use InvalidArgumentException;

/**
 * RFC 4180 CSV with a header row. Text cells that a spreadsheet would run
 * as a formula (=, +, -, @, tab, carriage return) are prefixed with an
 * apostrophe; numbers and numeric strings are written as they are.
 *
 *     $stream = fopen('php://output', 'wb');
 *     app(CsvWriter::class)->write(app(TableManager::class)->export('subjects', $state), $stream);
 */
final readonly class CsvWriter implements ExportWriter
{
    public function __construct(private ?int $limit = null) {}

    public function write(TableExport $export, $stream): int
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('CsvWriter needs a writable stream.');
        }

        fputcsv($stream, array_map(self::cell(...), $export->headings()), escape: '');
        $count = 0;

        foreach ($export->rows($this->limit) as $row) {
            fputcsv($stream, array_map(self::cell(...), $row), escape: '');
            $count++;
        }

        return $count;
    }

    public static function cell(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
