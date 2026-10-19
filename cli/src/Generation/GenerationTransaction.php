<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\Exceptions\CliException;
use Gcego\Cli\ExitCode;
use Throwable;

/**
 * Writes a plan's files all-or-nothing, as far as a filesystem allows:
 *
 *  1. refuse the whole plan if it has errors or conflicts (nothing written);
 *  2. re-check every target (still inside the application, no symlink) and
 *     stage each file's contents in a temporary file next to its target;
 *  3. move the staged files into place, keeping a backup of overwritten ones;
 *  4. on any failure, undo: remove files this run created (and directories
 *     it created), restore backups, delete temporary files.
 *
 * The undo is compensating, not atomic: a crash between steps can leave
 * *.gcego-tmp files, which are named for it and safe to delete.
 */
final readonly class GenerationTransaction
{
    public function __construct(private PathResolver $paths) {}

    public function commit(GenerationPlan $plan): GenerationResult
    {
        if (! $plan->valid()) {
            return $this->refused($plan, $plan->errors, ExitCode::InvalidInput);
        }

        if ($plan->conflicts() !== []) {
            return $this->refused($plan, array_map(fn (PlannedFile $file): string => sprintf('%s already exists and differs; it was kept. Inspect it, choose another name, or pass --force to overwrite it.', $file->relativePath), $plan->conflicts()), ExitCode::FileConflict);
        }

        $staged = [];
        $createdDirectories = [];
        $moved = [];
        $backups = [];

        try {
            foreach ($plan->writes() as $file) {
                $target = $this->paths->resolve($file->path);
                $directory = dirname($target);

                if (! is_dir($directory)) {
                    $createdDirectories = [...$createdDirectories, ...$this->makeDirectory($directory)];
                }

                $temporary = $target.'.'.bin2hex(random_bytes(4)).'.gcego-tmp';

                if (file_put_contents($temporary, $file->contents, LOCK_EX) !== strlen($file->contents)) {
                    throw new \RuntimeException(sprintf('Could not write %s.', $file->relativePath));
                }

                $staged[] = [$file, $target, $temporary];
            }

            foreach ($staged as [$file, $target, $temporary]) {
                $this->paths->resolve($target);   // nothing was swapped for a link meanwhile

                if (is_file($target)) {
                    $backup = $target.'.'.bin2hex(random_bytes(4)).'.gcego-tmp';

                    if (! copy($target, $backup)) {
                        throw new \RuntimeException(sprintf('Could not back up %s.', $file->relativePath));
                    }

                    $backups[$target] = $backup;
                }

                if (! rename($temporary, $target)) {
                    throw new \RuntimeException(sprintf('Could not move %s into place.', $file->relativePath));
                }

                $moved[] = $target;
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($moved) as $target) {
                isset($backups[$target]) ? rename($backups[$target], $target) : @unlink($target);
            }

            foreach ($staged as [, , $temporary]) {
                @unlink($temporary);
            }

            foreach ($backups as $backup) {
                @unlink($backup);
            }

            foreach (array_reverse($createdDirectories) as $directory) {
                @rmdir($directory);
            }

            $code = $exception instanceof CliException ? $exception->exitCode() : ExitCode::Failure;

            return $this->refused($plan, [$exception->getMessage().' Nothing was changed.'], $code);
        }

        foreach ($backups as $backup) {
            @unlink($backup);
        }

        $created = $overwritten = $skipped = [];

        foreach ($plan->files as $file) {
            match ($file->operation) {
                FileOperation::Create => $created[] = $file->relativePath,
                FileOperation::Overwrite => $overwritten[] = $file->relativePath,
                default => $skipped[] = $file->relativePath,
            };
        }

        return new GenerationResult($plan->generator, true, $created, $overwritten, $skipped, [], $plan->warnings, [], $plan->nextSteps);
    }

    /**
     * The result of a dry run: what commit() would do, nothing written.
     */
    public function preview(GenerationPlan $plan): GenerationResult
    {
        $conflicts = array_map(static fn (PlannedFile $file): string => $file->relativePath, $plan->conflicts());
        $exit = match (true) {
            ! $plan->valid() => ExitCode::InvalidInput,
            $conflicts !== [] => ExitCode::FileConflict,
            default => ExitCode::Success,
        };

        return new GenerationResult(
            $plan->generator,
            $exit === ExitCode::Success,
            array_map(static fn (PlannedFile $file): string => $file->relativePath, array_values(array_filter($plan->files, static fn (PlannedFile $file): bool => $file->operation === FileOperation::Create))),
            array_map(static fn (PlannedFile $file): string => $file->relativePath, array_values(array_filter($plan->files, static fn (PlannedFile $file): bool => $file->operation === FileOperation::Overwrite))),
            array_map(static fn (PlannedFile $file): string => $file->relativePath, array_values(array_filter($plan->files, static fn (PlannedFile $file): bool => $file->operation === FileOperation::Unchanged))),
            $conflicts,
            $plan->warnings,
            $plan->errors,
            $plan->nextSteps,
            true,
            $exit,
        );
    }

    /**
     * @param  list<string>  $errors
     */
    private function refused(GenerationPlan $plan, array $errors, int $exitCode): GenerationResult
    {
        return new GenerationResult($plan->generator, false, [], [], [], array_map(static fn (PlannedFile $file): string => $file->relativePath, $plan->conflicts()), $plan->warnings, $errors, [], false, $exitCode);
    }

    /**
     * Creates $directory and missing parents; returns those created, outermost first.
     *
     * @return list<string>
     */
    private function makeDirectory(string $directory): array
    {
        $missing = [];

        for ($current = $directory; ! is_dir($current) && $current !== dirname($current); $current = dirname($current)) {
            array_unshift($missing, $current);
        }

        foreach ($missing as $path) {
            $this->paths->resolve($path);

            if (! @mkdir($path, 0755) && ! is_dir($path)) {
                throw new \RuntimeException(sprintf('Could not create the directory %s.', $this->paths->relative($path)));
            }
        }

        return $missing;
    }
}
