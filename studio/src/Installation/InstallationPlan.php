<?php

declare(strict_types=1);

namespace Gcego\Studio\Installation;

/**
 * What gcego:install would do, computed without side effects.
 */
final readonly class InstallationPlan
{
    /**
     * @param  list<PlannedFile>  $files
     * @param  list<string>  $problems  Blocking (missing packages, invalid options).
     * @param  list<string>  $nextSteps
     */
    public function __construct(public array $files, public array $problems, public array $nextSteps) {}

    public function installed(): bool
    {
        return $this->files !== [] && array_filter($this->files, static fn (PlannedFile $file): bool => $file->status === PlannedFile::Create) === [];
    }

    /**
     * @return list<PlannedFile>
     */
    public function withStatus(string $status): array
    {
        return array_values(array_filter($this->files, static fn (PlannedFile $file): bool => $file->status === $status));
    }
}
