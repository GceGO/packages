<?php

declare(strict_types=1);

namespace Gcego\Testing\Diagnostics;

final readonly class TestingDiagnostic
{
    public const string Ok = 'ok';

    public const string Info = 'info';

    public const string Warning = 'warning';

    public const string Error = 'error';

    public function __construct(public string $check, public string $level, public string $message) {}

    /**
     * @return array{check: string, level: string, message: string}
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'level' => $this->level, 'message' => $this->message];
    }
}
