<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-metric ActiveStudents --dataset=student-performance
 */
final class MakeMetricCommand extends MakeCommand
{
    protected $description = 'Create an analytics metric (gcego/analytics)';

    public function __construct()
    {
        $this->signature = 'gcego:make-metric {name : The name, e.g. Subject}
        {--dataset= : Dataset it measures}
        {--model= : Or: an Eloquent model}
        {--aggregate=count : count, sum, avg, min, max, count_distinct}
        {--field= : Field (all but count)}
        {--ability= : Ability (model metrics)}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'metric';
    }
}
