<?php

declare(strict_types=1);

namespace Gcego\Analytics\Exports;

use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Writes export rows to a local temporary file: CSV (built in) or XLSX
 * (openspout, when installed). Text cells that a spreadsheet would run as
 * a formula (= + - @, tab, carriage return) are prefixed with an apostrophe
 * (CSV/formula injection); numbers are written as numbers.
 */
final class ExportWriter
{
    /** @var resource|null */
    private $handle = null;

    private ?XlsxWriter $xlsx = null;

    private int $rows = 0;

    public function __construct(public readonly string $format, public readonly string $path)
    {
        if ($format === 'xlsx') {
            if (! self::supports('xlsx')) {
                throw AnalyticsQueryException::rejected('format', 'XLSX exports need openspout/openspout.');
            }

            $this->xlsx = new XlsxWriter;
            $this->xlsx->openToFile($path);

            return;
        }

        if ($format !== 'csv') {
            throw AnalyticsQueryException::rejected('format', 'Unknown export format.');
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('The export file could not be created.');
        }

        // A byte order mark lets spreadsheet software read UTF-8 correctly.
        fwrite($handle, "\u{FEFF}");
        $this->handle = $handle;
    }

    public static function supports(string $format): bool
    {
        return $format === 'csv' || ($format === 'xlsx' && class_exists(XlsxWriter::class));
    }

    public static function extension(string $format): string
    {
        return $format === 'xlsx' ? 'xlsx' : 'csv';
    }

    public static function mime(string $format): string
    {
        return $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8';
    }

    /**
     * @param  list<int|float|string|null>  $cells
     */
    public function row(array $cells): void
    {
        $cells = array_map(self::safe(...), $cells);

        if ($this->xlsx !== null) {
            $this->xlsx->addRow(Row::fromValues($cells));
        } elseif ($this->handle !== null) {
            fputcsv($this->handle, array_map(static fn (int|float|string|null $cell): string => $cell === null ? '' : (string) $cell, $cells), ',', '"', '');
        }

        $this->rows++;
    }

    public function close(): int
    {
        $this->xlsx?->close();

        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }

        return $this->rows;
    }

    public static function safe(int|float|string|null $cell): int|float|string|null
    {
        if (! is_string($cell) || $cell === '') {
            return $cell;
        }

        return in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$cell : $cell;
    }
}
