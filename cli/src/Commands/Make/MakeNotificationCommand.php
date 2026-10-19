<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;

/**
 * php artisan gcego:make-notification WelcomeStudent --channels=database,mail
 */
final class MakeNotificationCommand extends MakeCommand
{
    protected $description = 'Create a notification (gcego/notifications)';

    public function __construct()
    {
        $this->signature = 'gcego:make-notification {name : The name, e.g. Subject}
        {--channels=database : toast, database, broadcast, mail (comma-separated)}
        {--type=info : success, error, warning, info or neutral}
        {--category=general : Category}
        {--title= : Title}
        {--body= : Body}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'notification';
    }
}
