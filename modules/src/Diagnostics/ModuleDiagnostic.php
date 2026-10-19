<?php

declare(strict_types=1);

namespace Gcego\Modules\Diagnostics;

/**
 * One structured finding about a module (names and class names only, never record values).
 */
final readonly class ModuleDiagnostic
{
    public const string Warning = 'warning';

    public const string Error = 'error';

    /**
     * @param  'warning'|'error'  $level
     * @param  string|null  $subject  "resource:academics.subjects", "page:academics-subjects", …
     */
    public function __construct(
        public string $check,
        public string $level,
        public string $message,
        public ?string $subject = null,
    ) {}

    /**
     * @return array{check: string, level: string, message: string, subject: string|null}
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'level' => $this->level, 'message' => $this->message, 'subject' => $this->subject];
    }
}
