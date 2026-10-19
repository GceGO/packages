<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-panel Staff --module=academics
 */
final class MakePanelCommand extends MakeCommand
{
    protected $description = 'Create a panel (gcego/panels), deny-by-default behind a Gate ability';

    public function __construct()
    {
        $this->signature = 'gcego:make-panel {name : The name, e.g. Subject}
        {--title= : Title}
        {--path= : URL path (default: the name)}
        {--domain= : Domain}
        {--guard= : Authentication guard}
        {--tenant= : optional or required}
        {--layout= : sidebar, topbar or compact}
        {--theme= : gcego/chroma theme}
        {--ability= : Gate ability to enter (default: access-<name>)}
        {--module=* : Modules to list}
        {--page=* : Pages to list}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'panel';
    }
}
