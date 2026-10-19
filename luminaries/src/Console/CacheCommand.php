<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Providers\BladeIconSetProvider;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:icons:cache   (also run by php artisan optimize)
 */
final class CacheCommand extends Command
{
    protected $signature = 'gcego:icons:cache';

    protected $description = 'Cache the icon manifest so icon lookups and discovery avoid the filesystem';

    public function handle(Luminaries $luminaries): int
    {
        $providers = [];

        foreach ($luminaries->providers() as $name => $provider) {
            if ($provider instanceof BladeIconSetProvider) {
                $providers[$name] = $provider->icons();
            }
        }

        try {
            $luminaries->manifest()->write($providers);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $luminaries->flush();
        $this->components->info(sprintf('Icon manifest cached: %d icons from %d providers.', array_sum(array_map(count(...), $providers)), count($providers)));

        return self::SUCCESS;
    }
}
