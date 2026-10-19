<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-form SubjectForm --module=academics --model="App\\Models\\Subject"
 */
final class MakeFormCommand extends MakeCommand
{
    protected $description = 'Create a reusable form (gcego/forms) in a module';

    public function __construct()
    {
        $this->signature = 'gcego:make-form {name : The name, e.g. Subject}
        {--module= : Module whose Forms/ folder holds it (required)}
        {--model= : Eloquent model to derive fields from}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'form';
    }

    protected function prompts(): array
    {
        return [
            'module' => fn (): ?string => $this->choose('Which module?', 'module', 'create one with php artisan gcego:make-module.'),
        ];
    }
}
