<?php

declare(strict_types=1);

namespace Gcego\Studio\Installation;

/**
 * A file the installer may create. $status is decided at planning time:
 * create (missing), unchanged (identical content), conflict (different
 * content: kept unless --force), overwrite (conflict with --force).
 */
final readonly class PlannedFile
{
    public const string Create = 'create';

    public const string Unchanged = 'unchanged';

    public const string Conflict = 'conflict';

    public const string Overwrite = 'overwrite';

    public function __construct(
        public string $path,
        public string $contents,
        public string $description,
        public string $status = self::Create,
    ) {}

    public function withStatus(string $status): self
    {
        return new self($this->path, $this->contents, $this->description, $status);
    }

    public function writes(): bool
    {
        return $this->status === self::Create || $this->status === self::Overwrite;
    }
}
