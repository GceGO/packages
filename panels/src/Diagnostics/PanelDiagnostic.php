<?php

declare(strict_types=1);

namespace Gcego\Panels\Diagnostics;

/**
 * One finding about a panel ("error": it cannot work as declared; "warning": it works, but check it).
 */
final readonly class PanelDiagnostic
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
