<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\ExitCode;

/**
 * What a generation did.
 */
final readonly class GenerationResult
{
    /**
     * @param  list<string>  $created  relative paths
     * @param  list<string>  $overwritten
     * @param  list<string>  $skipped  unchanged files
     * @param  list<string>  $conflicts  files that exist and differ (nothing was written)
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @param  list<string>  $nextSteps
     */
    public function __construct(
        public string $generator,
        public bool $successful,
        public array $created = [],
        public array $overwritten = [],
        public array $skipped = [],
        public array $conflicts = [],
        public array $warnings = [],
        public array $errors = [],
        public array $nextSteps = [],
        public bool $dryRun = false,
        public int $exitCode = ExitCode::Success,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generator' => $this->generator,
            'successful' => $this->successful,
            'dry_run' => $this->dryRun,
            'created' => $this->created,
            'overwritten' => $this->overwritten,
            'skipped' => $this->skipped,
            'conflicts' => $this->conflicts,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'next_steps' => $this->nextSteps,
            'exit_code' => $this->exitCode,
        ];
    }
}
