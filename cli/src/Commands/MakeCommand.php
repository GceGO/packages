<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\CliManager;
use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\GenerationTransaction;
use Gcego\Cli\Inspection\Catalog;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:make <generator> <name> [--set=option=value]* [--dry-run] [--force]
 * — runs any generator, including ones packages register; --list shows them.
 */
final class MakeCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:make
        {generator? : Generator id (see --list)}
        {name? : The name}
        {--set=* : Generator options as option=value (flags: option=true)}
        {--list : List the generators}
        {--force : Overwrite files that exist and differ}
        {--dry-run : Show what would be written, write nothing}
        {--format=text : Output format: text or json}';

    protected $description = 'Run any GCEGO generator, including generators contributed by packages';

    public function handle(CliManager $cli, GenerationTransaction $transaction, Catalog $catalog): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $id = $this->argument('generator');
        $name = $this->argument('name');

        if ($this->option('list') || ! is_string($id)) {
            $generators = array_map(static fn ($generator): array => ['id' => $generator->id(), 'description' => $generator->description(), 'custom' => $cli->isCustom($generator->id()), 'requires' => $generator->requires(), 'options' => $generator->options()], array_values($cli->generators()));

            if ($this->json()) {
                $this->emit(['generators' => $generators]);
            } else {
                foreach ($generators as $generator) {
                    $this->components->twoColumnDetail($generator['id'].($generator['custom'] ? ' <fg=gray>(custom)</>' : ''), $generator['description']);
                }
            }

            return is_string($id) || $this->option('list') ? ExitCode::Success : ExitCode::InvalidInput;
        }

        try {
            $options = [];

            foreach ((array) $this->option('set') as $pair) {
                if (! is_string($pair) || preg_match('/^([a-z][a-z0-9-]*)=(.*)$/s', $pair, $match) !== 1) {
                    throw GenerationException::invalid(sprintf('--set "%s" must be option=value.', is_string($pair) ? $pair : ''));
                }

                $options[$match[1]] = match ($match[2]) {
                    'true' => true,
                    'false' => false,
                    default => $match[2],
                };
            }

            $plan = $cli->resolve($id)->plan(new GenerationContext(is_string($name) ? $name : '', $options, (bool) $this->option('force')));
            $result = $this->option('dry-run') ? $transaction->preview($plan) : $transaction->commit($plan);
            $catalog->refresh();
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        if ($this->json()) {
            $this->emit([...$result->toArray(), 'files' => array_map(static fn ($file): array => $file->toArray(), $plan->files)]);

            return $result->exitCode;
        }

        foreach ($plan->files as $file) {
            $this->components->twoColumnDetail($file->relativePath, $result->dryRun ? 'would '.$file->operation->value : $file->operation->value);
        }

        foreach ([...$result->warnings, ...$result->errors] as $message) {
            $this->components->warn($message);
        }

        foreach ($result->successful ? $result->nextSteps : [] as $index => $step) {
            $this->line(sprintf('  %d. %s', $index + 1, $step));
        }

        return $result->exitCode;
    }
}
