<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-infolist SubjectDetails --module=academics --model="App\\Models\\Subject"
 */
final class MakeInfolistCommand extends MakeCommand
{
    protected $description = 'Create reusable record details (gcego/infolists) in a module';

    public function __construct()
    {
        $this->signature = 'gcego:make-infolist {name : The name, e.g. Subject}
        {--module= : Module whose Infolists/ folder holds it (required)}
        {--model= : Eloquent model to derive entries from}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'infolist';
    }

    protected function prompts(): array
    {
        return [
            'module' => fn (): ?string => $this->choose('Which module?', 'module', 'create one with php artisan gcego:make-module.'),
        ];
    }
}
