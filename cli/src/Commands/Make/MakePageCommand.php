<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-page Reports --ability=view-reports --panel=admin
 */
final class MakePageCommand extends MakeCommand
{
    protected $description = 'Create a page (gcego/pages)';

    public function __construct()
    {
        $this->signature = 'gcego:make-page {name : The name, e.g. Subject}
        {--type=page : page or dashboard}
        {--title= : Title}
        {--ability= : Gate ability required to open it (default: signed in)}
        {--panel= : Panel to check it is listed in}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'page';
    }
}
