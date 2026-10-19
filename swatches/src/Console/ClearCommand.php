<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\SwatchesManager;
use Illuminate\Console\Command;

final class ClearCommand extends Command
{
    protected $signature = 'gcego:swatches:clear';

    protected $description = 'Remove the compiled Swatches cache file';

    public function handle(SwatchesManager $swatches): int
    {
        if (! $swatches->clearCache()) {
            $this->components->error('The Swatches cache file could not be removed: '.$swatches->cache()->path);

            return self::FAILURE;
        }

        $this->components->info('Swatches cache cleared.');

        return self::SUCCESS;
    }
}
