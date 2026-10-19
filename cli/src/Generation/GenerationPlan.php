<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

/**
 * Everything a generation would do, decided before anything is written:
 * the files and what writing each means, the packages it relies on,
 * warnings, errors and the next steps. Plans are deterministic for the same
 * input and files on disk, and are what --dry-run prints.
 */
final readonly class GenerationPlan
{
    /**
     * @param  list<PlannedFile>  $files
     * @param  list<string>  $requires  GCEGO packages the generated code uses
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @param  list<string>  $nextSteps
     * @param  array<string, mixed>  $inputs
     */
    public function __construct(
        public string $generator,
        public array $files,
        public array $requires = [],
        public array $warnings = [],
        public array $errors = [],
        public array $nextSteps = [],
        public array $inputs = [],
    ) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<PlannedFile>
     */
    public function conflicts(): array
    {
        return array_values(array_filter($this->files, static fn (PlannedFile $file): bool => $file->operation === FileOperation::Conflict));
    }

    /**
     * @return list<PlannedFile>
     */
    public function writes(): array
    {
        return array_values(array_filter($this->files, static fn (PlannedFile $file): bool => $file->operation->writes()));
    }

    /**
     * @return array{generator: string, valid: bool, inputs: array<string, mixed>, files: list<array<string, mixed>>, requires: list<string>, warnings: list<string>, errors: list<string>, next_steps: list<string>}
     */
    public function toArray(): array
    {
        return [
            'generator' => $this->generator,
            'valid' => $this->valid() && $this->conflicts() === [],
            'inputs' => $this->inputs,
            'files' => array_map(static fn (PlannedFile $file): array => $file->toArray(), $this->files),
            'requires' => $this->requires,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'next_steps' => $this->nextSteps,
        ];
    }
}
