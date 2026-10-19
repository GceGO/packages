<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-layout AcademicDashboard --columns=3
 */
final class MakeLayoutCommand extends MakeCommand
{
    protected $description = 'Create a layout (gcego/layouts)';

    public function __construct()
    {
        $this->signature = 'gcego:make-layout {name : The name, e.g. Subject}
        {--columns=2 : Grid columns, 1 to 4}
        {--ability= : Gate ability to see it (default: signed in)}
        {--title= : Label}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'layout';
    }
}
