<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Concerns;

use Gcego\Cli\CliManager;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\GenerationResult;
use Gcego\Cli\Generation\GenerationTransaction;
use Gcego\Cli\Generation\PlannedFile;
use Gcego\Cli\Inspection\Catalog;
use Illuminate\Console\Command;
use Throwable;

/**
 * A gcego:make-* command: collects input (options, or prompts when
 * interactive), lets the generator plan, then previews (--dry-run) or
 * writes the plan through a GenerationTransaction. It never overwrites a
 * different file without --force, and refuses to write in production
 * unless confirmed interactively.
 */
abstract class MakeCommand extends Command
{
    use RendersOutput;

    /** Options every make command has (appended to $signature). */
    protected const string Common = '
        {--force : Overwrite files that exist and differ}
        {--dry-run : Show what would be written, write nothing}
        {--test : Also generate a test (gcego/testing)}
        {--pest : Generate the test with Pest}
        {--phpunit : Generate the test with PHPUnit}
        {--format=text : Output format: text or json}';

    abstract protected function generator(): string;

    /**
     * Prompts for options missing in interactive mode: option => closure returning the answer.
     *
     * @return array<string, \Closure(): (string|null)>
     */
    protected function prompts(): array
    {
        return [];
    }

    public function handle(CliManager $cli, GenerationTransaction $transaction, Catalog $catalog): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $generator = $cli->resolve($this->generator());
            $options = $this->collect($generator->options());
            $context = new GenerationContext($this->nameArgument(), $options, (bool) $this->option('force'));
            $plan = $generator->plan($context);
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        if ($this->option('dry-run')) {
            return $this->report($transaction->preview($plan), $plan->files);
        }

        if ($plan->writes() !== [] && $this->laravel->environment('production') && ! $this->confirmProduction()) {
            return $this->failed(GenerationException::invalid('Refusing to write files in production. Generate definitions in development and deploy them; or confirm interactively.'));
        }

        $result = $transaction->commit($plan);
        $catalog->refresh();

        return $this->report($result, $plan->files);
    }

    protected function nameArgument(): string
    {
        $name = $this->argument('name');

        return is_string($name) ? $name : '';
    }

    /**
     * @param  array<string, string>  $declared
     * @return array<string, string|bool|list<string>|null>
     */
    private function collect(array $declared): array
    {
        $options = [];

        foreach ([...array_keys($declared), 'test', 'pest', 'phpunit'] as $option) {
            if ($this->hasOption($option)) {
                $value = $this->option($option);
                $options[$option] = is_array($value) ? array_values(array_filter($value, 'is_string')) : (is_string($value) || is_bool($value) ? $value : null);
            }
        }

        if ($this->input->isInteractive()) {
            foreach ($this->prompts() as $option => $prompt) {
                if (($options[$option] ?? null) === null || $options[$option] === '') {
                    $options[$option] = $prompt();
                }
            }
        }

        return $options;
    }

    private function confirmProduction(): bool
    {
        return $this->input->isInteractive() && $this->confirm('The application is in production. Write the generated files anyway?', false);
    }

    /**
     * @param  list<PlannedFile>  $files
     */
    private function report(GenerationResult $result, array $files): int
    {
        if ($this->json()) {
            $this->emit([...$result->toArray(), 'files' => array_map(static fn ($file): array => $file->toArray(), $files)]);

            return $result->exitCode;
        }

        if (! $result->successful && $result->conflicts !== [] && ! $result->dryRun) {
            $this->newLine();
            $this->line('<fg=red;options=bold>GCEGO GENERATION FAILED</>');
            $this->line(sprintf('Generator: %s — %s', $result->generator, $this->nameArgument()));
            $this->line('Reason: a target file already exists with different contents. Nothing was written.');
            $this->newLine();

            foreach ($result->conflicts as $conflict) {
                $this->line('  '.$conflict);
            }

            $this->newLine();
            $this->line('Options:');
            $this->line('  - Choose another name.');
            $this->line('  - Inspect the existing file.');
            $this->line('  - Use --force to overwrite it deliberately.');

            return $result->exitCode;
        }

        $this->components->info(sprintf('%s: %s%s', $result->dryRun ? 'Dry run' : 'GCEGO generation', $result->generator, $result->dryRun ? ' (nothing written)' : ''));

        foreach ($files as $file) {
            $this->components->twoColumnDetail(sprintf('%s <fg=gray>%s</>', $file->relativePath, $file->description), match ($file->operation->value) {
                'create' => $result->dryRun ? '<fg=green>would create</>' : '<fg=green>created</>',
                'overwrite' => $result->dryRun ? '<fg=yellow>would overwrite</>' : '<fg=yellow>overwritten</>',
                'unchanged' => '<fg=gray>unchanged</>',
                default => '<fg=red>conflict: exists and differs</>',
            });

            if ($file->class !== null) {
                $this->line(sprintf('    <fg=gray>class %s</>', $file->class));
            }
        }

        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($result->errors as $error) {
            $this->components->error($error);
        }

        if ($result->successful && $result->nextSteps !== []) {
            $this->line('Next steps:');

            foreach ($result->nextSteps as $index => $step) {
                $this->line(sprintf('  %d. %s', $index + 1, $step));
            }
        }

        return $result->exitCode;
    }

    /**
     * Existing names of a kind, for prompts.
     *
     * @return list<string>
     */
    protected function existing(string $kind): array
    {
        return $this->laravel->make(Catalog::class)->names($kind);
    }

    protected function choose(string $question, string $kind, string $create): ?string
    {
        $names = $this->existing($kind);

        if ($names === []) {
            $this->components->warn(sprintf('No %s exists yet: %s', $kind, $create));

            return null;
        }

        $answer = $this->choice($question, $names, 0);

        return is_string($answer) ? $answer : null;
    }

    protected function exitCodeFor(GenerationResult $result): int
    {
        return $result->successful ? ExitCode::Success : $result->exitCode;
    }
}
