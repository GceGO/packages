<?php

declare(strict_types=1);

namespace Gcego\Tables\Contracts;

use Gcego\Tables\Export\TableExport;

/**
 * Writes an authorized TableExport somewhere (a stream, a file, a spreadsheet).
 * Queued exports and downloads belong to later packages; they receive a
 * TableExport built for the requesting user and write it with a writer.
 */
interface ExportWriter
{
    /**
     * @param  resource  $stream
     * @return int Rows written.
     */
    public function write(TableExport $export, $stream): int;
}
