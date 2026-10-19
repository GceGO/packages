<?php

declare(strict_types=1);

namespace Gcego\Settings\Console;

use Gcego\Settings\Cache\SettingsCache;
use Illuminate\Console\Command;

final class ClearSettingsCacheCommand extends Command
{
    protected $signature = 'gcego:settings:clear-cache';

    protected $description = 'Invalidate every cached settings value on every worker';

    public function handle(SettingsCache $cache): int
    {
        $cache->flush();
        $this->components->info('Cached settings were invalidated.');

        return self::SUCCESS;
    }
}
