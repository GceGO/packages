<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-navigation AcademicNavigation --item="Subjects=/admin/subjects"
 */
final class MakeNavigationCommand extends MakeCommand
{
    protected $description = 'Create a navigation menu (gcego/navigation)';

    public function __construct()
    {
        $this->signature = 'gcego:make-navigation {name : The name, e.g. Subject}
        {--item=* : Label=route.name or Label=/path}
        {--permission= : Permission required to see each item}
        {--title= : Group label}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'navigation';
    }
}
