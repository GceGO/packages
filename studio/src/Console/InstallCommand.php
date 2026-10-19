<?php

declare(strict_types=1);

namespace Gcego\Studio\Console;

use Gcego\Studio\Installation\InstallationManager;
use Gcego\Studio\Installation\InstallationResult;
use Gcego\Studio\Installation\PlannedFile;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * php artisan gcego:install — publishes Studio's configuration and creates
 * a starter panel and dashboard (and, with --model, a read-only example
 * module). Safe to re-run: existing identical files are left alone,
 * different ones are kept unless you confirm or pass --force.
 */
final class InstallCommand extends Command
{
    protected $signature = 'gcego:install
        {--panel= : The starter panel name (default: gcego-studio.install.panel)}
        {--model= : Create a read-only example module for this Eloquent model class}
        {--module= : The example module folder name (default: the model\'s plural)}
        {--dry-run : Show what would be done, without writing anything}
        {--force : Overwrite files whose content differs from the starter files}';

    protected $description = 'Install GCEGO Admin Studio: configuration, a starter panel and dashboard (no users or permissions are created)';

    public function handle(InstallationManager $installer): int
    {
        $panel = $this->option('panel');
        $model = $this->option('model');
        $module = $this->option('module');
        $plan = $installer->plan(is_string($panel) ? $panel : null, is_string($module) ? $module : null, is_string($model) && is_subclass_of($model, Model::class) ? $model : (is_string($model) ? '' : null), (bool) $this->option('force'));

        $this->components->info('GCEGO Admin Studio installation');

        foreach ($plan->files as $file) {
            $this->components->twoColumnDetail($file->description.' <fg=gray>'.$installer->relative($file->path).'</>', match ($file->status) {
                PlannedFile::Create => '<fg=green>create</>',
                PlannedFile::Unchanged => '<fg=gray>already installed</>',
                PlannedFile::Overwrite => '<fg=yellow>overwrite (--force)</>',
                default => '<fg=yellow>exists, differs: kept</>',
            });
        }

        if ($plan->problems !== []) {
            foreach ($plan->problems as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $confirmed = [];

        if ($this->input->isInteractive() && ! $this->option('force')) {
            foreach ($plan->withStatus(PlannedFile::Conflict) as $file) {
                if ($this->confirm(sprintf('%s already exists and differs. Overwrite it?', $installer->relative($file->path)), false)) {
                    $confirmed[] = $file->path;
                }
            }
        }

        $result = $installer->install($plan, $confirmed);

        foreach ($result->errors as $error) {
            $this->components->error($error);
        }

        foreach ($result->kept as $kept) {
            $this->components->warn(sprintf('Kept your %s (use --force to replace it).', $kept));
        }

        $this->components->info(match ($result->status) {
            InstallationResult::Installed => $result->created === [] && $result->overwritten === [] ? 'GCEGO Admin Studio is already installed.' : 'GCEGO Admin Studio is installed.',
            InstallationResult::Partial => 'Installed, except the files listed above.',
            default => 'Installation failed.',
        });

        if ($result->status !== InstallationResult::Failed) {
            $this->line('Next steps:');

            foreach ($result->nextSteps as $index => $step) {
                $this->line(sprintf('  %d. %s', $index + 1, $step));
            }
        }

        return $result->errors === [] && $result->status !== InstallationResult::Failed ? self::SUCCESS : self::FAILURE;
    }
}
