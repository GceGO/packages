<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-blueprint Subjects --model="App\\Models\\Subject"
 */
final class MakeBlueprintCommand extends MakeCommand
{
    protected $description = 'Create a blueprint (gcego/blueprints)';

    public function __construct()
    {
        $this->signature = 'gcego:make-blueprint {name : The name, e.g. Subject}
        {--model= : Eloquent model: adds a Source, table and form}
        {--module= : Place it in this module\'s folder}
        {--title= : Title}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'blueprint';
    }
}
