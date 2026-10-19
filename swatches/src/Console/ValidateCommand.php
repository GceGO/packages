<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\Console\Concerns\FormatsOutput;
use Gcego\Swatches\Exceptions\SwatchesException;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Validation\Severity;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:validate [theme] [--strict] [--format=json]   (exits 1 on errors; with --strict also on warnings)
 */
final class ValidateCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:swatches:validate
        {theme? : One theme (default: every registered theme)}
        {--strict : Fail on warnings too}
        {--apca : Also warn about pairs below APCA lightness contrast levels}
        {--color-vision : Also warn about colors that collapse with simulated color-vision deficiencies}
        {--format=text : Output format: text or json}';

    protected $description = 'Validate color themes: references, inheritance, gamut and WCAG 2.2 AA contrast';

    public function handle(SwatchesManager $swatches): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $theme = $this->argument('theme');

        try {
            $report = $swatches->validate(
                is_string($theme) ? $theme : null,
                $this->option('apca') === true ? true : null,
                $this->option('color-vision') === true ? true : null,
            );
        } catch (SwatchesException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $failed = ! $report->passes() || ($this->option('strict') === true && $report->warnings() !== []);

        if ($this->json()) {
            $this->emit($report->toArray());

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        foreach ($report->issues as $issue) {
            $line = sprintf('[%s%s] %s%s', $issue->theme, $issue->mode !== null ? ' · '.$issue->mode : '', $issue->message, $issue->suggestion !== null ? ' '.$issue->suggestion : '');
            $issue->severity === Severity::Error ? $this->components->error($line) : $this->components->warn($line);
        }

        $message = sprintf('%s · %d contrast pairs checked.', $report->summary(), count($report->contrast));
        $failed ? $this->components->error($message) : $this->components->info($message);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
