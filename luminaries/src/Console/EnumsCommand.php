<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Support\IconEnumGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * php artisan gcego:icons:enums           regenerate Gcego\Luminaries\Icons\* from the installed icon sets
 * php artisan gcego:icons:enums --check   fail when they are out of date (CI)
 */
final class EnumsCommand extends Command
{
    protected $signature = 'gcego:icons:enums
        {--check : Only report enums that differ from the installed icon sets; exit 1 when one does}
        {--path= : Directory to write to (default: the package\'s src/Icons)}';

    protected $description = 'Generate the typed icon enums (Heroicon, Lucide, Alias...) from the installed icon sets';

    public function handle(IconEnumGenerator $generator, Filesystem $files): int
    {
        $path = $this->option('path');
        $directory = is_string($path) && $path !== '' ? rtrim($path, '/') : dirname(__DIR__).'/Icons';
        $stale = [];

        foreach ($generator->all() as $enum => $source) {
            $file = $directory.'/'.$enum.'.php';

            if ($files->exists($file) && $files->get($file) === $source) {
                continue;
            }

            $stale[] = $enum;

            if (! $this->option('check')) {
                $files->ensureDirectoryExists($directory);
                $files->replace($file, $source);
            }
        }

        if ($this->option('check')) {
            if ($stale !== []) {
                $this->components->error(sprintf('Out of date: %s. Run php artisan gcego:icons:enums.', implode(', ', $stale)));

                return self::FAILURE;
            }

            $this->components->info('Icon enums are up to date.');

            return self::SUCCESS;
        }

        $this->components->info($stale === [] ? 'Icon enums are already up to date.' : sprintf('Generated %s in %s.', implode(', ', $stale), $directory));

        return self::SUCCESS;
    }
}
