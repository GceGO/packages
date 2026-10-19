<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\SwatchesManager;
use Illuminate\Console\Command;

final class CacheCommand extends Command
{
    protected $signature = 'gcego:swatches:cache';

    protected $description = 'Compile the runtime CSS of registered themes and accents into the cache file';

    public function handle(SwatchesManager $swatches): int
    {
        $report = $swatches->validate();

        if (! $report->passes()) {
            $this->components->error('Fix the theme errors first ('.$report->summary().'): php artisan gcego:swatches:validate');

            return self::FAILURE;
        }

        $counts = $swatches->warmCache();
        $this->components->info(sprintf('Swatches cached: %d runtime theme(s), accent styles for %d theme(s).', $counts['themes'], $counts['accents']));

        return self::SUCCESS;
    }
}
