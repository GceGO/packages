<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;
use Illuminate\Support\Str;

/**
 * php artisan gcego:make-resource Subject --model="App\\Models\\Subject" --module=academics --panel=admin
 */
final class MakeResourceCommand extends MakeCommand
{
    protected $description = 'Create a resource for an Eloquent model: table, form, infolist and actions, authorized by its policy';

    public function __construct()
    {
        $this->signature = 'gcego:make-resource {name : The name, e.g. Subject}
        {--model= : Eloquent model (required)}
        {--module= : Module (required)}
        {--panel= : Panel to check it is listed in}
        {--read-only : No form, create or edit}
        {--delete : Add the Delete action}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'resource';
    }

    protected function prompts(): array
    {
        return [
            'module' => fn (): ?string => $this->choose('Which module?', 'module', 'create one with php artisan gcego:make-module.'),
            'model' => fn (): ?string => $this->modelPrompt(),
        ];
    }

    private function modelPrompt(): ?string
    {
        $default = 'App\\Models\\'.Str::studly(Str::singular($this->nameArgument()));
        $answer = $this->ask('Which Eloquent model?', class_exists($default) ? $default : null);

        return is_string($answer) ? $answer : null;
    }
}
