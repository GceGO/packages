<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\SwatchesManager;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:generate [--output=resources/css/gcego-swatches.css] [--check]
 * php artisan gcego:swatches:generate --package [--check]   (maintainers: regenerate resources/css)
 */
final class GenerateCommand extends Command
{
    protected $signature = 'gcego:swatches:generate
        {--output=resources/css/gcego-swatches.css : Application stylesheet, relative to the project root}
        {--package : Regenerate the package\'s own tokens.css, tailwind.css and themes.css}
        {--check : Do not write; fail when the files are out of date}';

    protected $description = 'Write build-time CSS for configured palettes and themes (import it in your Tailwind entry)';

    public function handle(SwatchesManager $swatches): int
    {
        $report = $swatches->validate();

        if (! $report->passes()) {
            $this->components->error('Fix the theme errors first ('.$report->summary().'): php artisan gcego:swatches:validate');

            return self::FAILURE;
        }

        $files = [];

        if ($this->option('package') === true) {
            foreach ($swatches->packageStylesheets() as $name => $css) {
                $files[dirname(__DIR__, 2).'/resources/css/'.$name] = $css;
            }
        } else {
            $output = (string) $this->option('output');

            if ($output === '' || str_contains($output, '..') || ! str_ends_with($output, '.css')) {
                $this->components->error('--output must be a .css path inside the project.');

                return self::INVALID;
            }

            $files[$this->laravel->basePath($output)] = $swatches->applicationStylesheet();
        }

        $stale = [];

        foreach ($files as $path => $css) {
            if (is_file($path) && file_get_contents($path) === $css) {
                continue;
            }

            $stale[] = $path;

            if ($this->option('check') !== true) {
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }

                file_put_contents($path, $css);
                $this->components->twoColumnDetail('Written', $path);
            }
        }

        if ($this->option('check') === true) {
            if ($stale !== []) {
                foreach ($stale as $path) {
                    $this->components->error('Out of date: '.$path);
                }

                return self::FAILURE;
            }

            $this->components->info('Generated CSS is up to date.');

            return self::SUCCESS;
        }

        $this->components->info($stale === [] ? 'Generated CSS was already up to date.' : sprintf('%d file(s) written.', count($stale)));

        return self::SUCCESS;
    }
}
