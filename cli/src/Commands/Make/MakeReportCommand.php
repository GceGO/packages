<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-report AcademicPerformance --dataset=student-performance
 */
final class MakeReportCommand extends MakeCommand
{
    protected $description = 'Create an analytics report (gcego/analytics)';

    public function __construct()
    {
        $this->signature = 'gcego:make-report {name : The name, e.g. Subject}
        {--dataset= : Dataset (required)}
        {--export : Allow CSV/XLSX export}
        {--ability= : Ability to run it (default <name>.view)}
        {--title= : Title}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'report';
    }

    protected function prompts(): array
    {
        return [
            'dataset' => fn (): ?string => $this->choose('Which dataset?', 'dataset', 'create one with php artisan gcego:make-dataset.'),
        ];
    }
}
