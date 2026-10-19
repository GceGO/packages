<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-settings AcademicSettings --scope=tenant --page
 */
final class MakeSettingsCommand extends MakeCommand
{
    protected $description = 'Create a settings group (gcego/settings)';

    public function __construct()
    {
        $this->signature = 'gcego:make-settings {name : The name, e.g. Subject}
        {--scope=global : global, panel, tenant or user}
        {--ability= : Ability to change them (default <name>.manage)}
        {--page : Also create its settings page}
        {--title= : Title}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'settings';
    }
}
