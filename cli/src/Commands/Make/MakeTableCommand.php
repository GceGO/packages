<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-table SubjectTable --module=academics --model="App\\Models\\Subject"
 */
final class MakeTableCommand extends MakeCommand
{
    protected $description = 'Create a reusable table (gcego/tables) in a module';

    public function __construct()
    {
        $this->signature = 'gcego:make-table {name : The name, e.g. Subject}
        {--module= : Module whose Tables/ folder holds it (required)}
        {--model= : Eloquent model to derive columns from}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'table';
    }

    protected function prompts(): array
    {
        return [
            'module' => fn (): ?string => $this->choose('Which module?', 'module', 'create one with php artisan gcego:make-module.'),
        ];
    }
}
