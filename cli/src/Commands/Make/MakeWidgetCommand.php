<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-widget StudentStats --type=trend --model="App\\Models\\Student"
 */
final class MakeWidgetCommand extends MakeCommand
{
    protected $description = 'Create a dashboard widget (gcego/widgets)';

    public function __construct()
    {
        $this->signature = 'gcego:make-widget {name : The name, e.g. Subject}
        {--type=stat : stat, trend, chart, progress, recent or custom}
        {--model= : Eloquent model to aggregate}
        {--blueprint= : Blueprint whose newest records a recent widget lists}
        {--ability= : Gate ability to see it (custom: required)}
        {--title= : Label}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'widget';
    }
}
