<?php

declare(strict_types=1);

namespace Gcego\Layouts\Diagnostics;

/**
 * One structured finding about a layout (paths and class names only, never values).
 */
final readonly class LayoutDiagnostic
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
