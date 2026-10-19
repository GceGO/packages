<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;
use Illuminate\Support\Str;

/**
 * php artisan gcego:make-dataset StudentPerformance --model="App\\Models\\AssessmentResult"
 */
final class MakeDatasetCommand extends MakeCommand
{
    protected $description = 'Create an analytics dataset (gcego/analytics)';

    public function __construct()
    {
        $this->signature = 'gcego:make-dataset {name : The name, e.g. Subject}
        {--model= : Eloquent model (required)}
        {--ability= : Ability to query it (default: viewAny on the model)}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'dataset';
    }

    protected function prompts(): array
    {
        return [
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
