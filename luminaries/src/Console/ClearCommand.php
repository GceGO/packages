<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Luminaries;
use Illuminate\Console\Command;

/**
 * php artisan gcego:icons:clear   (also run by php artisan optimize:clear)
 */
final class ClearCommand extends Command
{
    protected $signature = 'gcego:icons:clear';

    protected $description = 'Remove the cached icon manifest';

    public function handle(Luminaries $luminaries): int
    {
        $luminaries->manifest()->clear();
        $luminaries->flush();
        $this->components->info('Icon manifest cleared.');

        return self::SUCCESS;
    }
}
