<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Diagnostics;

/**
 * One finding of IconInspector.
 */
final readonly class Diagnostic
{
    public const string Error = 'error';

    public const string Warning = 'warning';

    public const string Info = 'info';

    public function __construct(
        public string $level,
        public string $area,
        public string $message,
        public ?string $subject = null,
    ) {}

    /**
     * @return array{level: string, area: string, message: string, subject: ?string}
     */
    public function toArray(): array
    {
        return ['level' => $this->level, 'area' => $this->area, 'message' => $this->message, 'subject' => $this->subject];
    }
}
