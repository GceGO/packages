<?php

declare(strict_types=1);

namespace Gcego\Studio\Installation;

/**
 * What gcego:install did. Status: installed (everything written or
 * already there), partial (some files kept because they differ, or a
 * write failed), failed (blocking problems, nothing written), planned (dry run).
 */
final readonly class InstallationResult
{
    public const string Installed = 'installed';

    public const string Partial = 'partial';

    public const string Failed = 'failed';

    public const string Planned = 'planned';

    /**
     * @param  list<string>  $created
     * @param  list<string>  $overwritten
     * @param  list<string>  $unchanged
     * @param  list<string>  $kept  Conflicting files left as they were.
     * @param  list<string>  $errors
     * @param  list<string>  $nextSteps
     */
    public function __construct(
        public string $status,
        public array $created = [],
        public array $overwritten = [],
        public array $unchanged = [],
        public array $kept = [],
        public array $errors = [],
        public array $nextSteps = [],
    ) {}

    public function successful(): bool
    {
        return in_array($this->status, [self::Installed, self::Planned], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'created' => $this->created, 'overwritten' => $this->overwritten, 'unchanged' => $this->unchanged, 'kept' => $this->kept, 'errors' => $this->errors, 'next_steps' => $this->nextSteps];
    }
}
