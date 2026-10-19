<?php

declare(strict_types=1);

namespace Gcego\Notifications\Diagnostics;

/**
 * One finding ("error": a feature cannot work; "warning": it works, but check it).
 */
final readonly class NotificationDiagnostic
{
    public function __construct(public string $level, public string $code, public string $message) {}

    public static function error(string $code, string $message): self
    {
        return new self('error', $code, $message);
    }

    public static function warning(string $code, string $message): self
    {
        return new self('warning', $code, $message);
    }
}
