<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-workflow ContentApproval --model="App\\Models\\Article"
 */
final class MakeWorkflowCommand extends MakeCommand
{
    protected $description = 'Create an approval workflow (gcego/workflows)';

    public function __construct()
    {
        $this->signature = 'gcego:make-workflow {name : The name, e.g. Subject}
        {--model= : The Eloquent subject (its policy authorizes transitions)}
        {--title= : Title}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'workflow';
    }
}
