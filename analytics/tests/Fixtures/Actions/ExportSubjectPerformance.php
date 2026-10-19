<?php

declare(strict_types=1);

namespace Gcego\Analytics\Tests\Fixtures\Actions;

use Gcego\Analytics\Integration\Actions\ExportReport;

final class ExportSubjectPerformance extends ExportReport
{
    protected function report(): string
    {
        return 'subject-performance';
    }
}
