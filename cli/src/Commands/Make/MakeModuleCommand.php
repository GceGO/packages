<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-module Academics --panel=admin
 */
final class MakeModuleCommand extends MakeCommand
{
    protected $description = 'Create a module (gcego/modules)';

    public function __construct()
    {
        $this->signature = 'gcego:make-module {name : The name, e.g. Subject}
        {--title= : Title}
        {--description= : Description}
        {--icon= : Icon name}
        {--navigation-group= : Navigation group}
        {--panel= : Panel that should list it}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'module';
    }
}
