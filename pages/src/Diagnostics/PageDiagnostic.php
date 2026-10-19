<?php

declare(strict_types=1);

namespace Gcego\Pages\Diagnostics;

/**
 * One structured finding about a page (paths, names and class names only, never record values).
 */
final readonly class PageDiagnostic
{
    public const string Warning = 'warning';

    public const string Error = 'error';

    /**
     * @param  'warning'|'error'  $level
     */
    public function __construct(
        public string $check,
        public string $level,
        public string $message,
        public ?string $path = null,
    ) {}

    /**
     * @return array{check: string, level: string, message: string, path: string|null}
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'level' => $this->level, 'message' => $this->message, 'path' => $this->path];
    }
}
