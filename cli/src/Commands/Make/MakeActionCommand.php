<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-action PublishSubject --module=academics
 */
final class MakeActionCommand extends MakeCommand
{
    protected $description = 'Create a record action (gcego/actions): handler class and definition';

    public function __construct()
    {
        $this->signature = 'gcego:make-action {name : The name, e.g. Subject}
        {--ability= : Policy ability that authorizes it (default: camelCase name)}
        {--module= : Also write the Action definition into this module}
        {--title= : Label}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'action';
    }
}
