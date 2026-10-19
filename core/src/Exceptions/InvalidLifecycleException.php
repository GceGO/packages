<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use Gcego\Core\Lifecycle\FrameworkState;
use LogicException;

class InvalidLifecycleException extends LogicException implements GcegoException
{
    public static function transition(FrameworkState $from, FrameworkState $to): self
    {
        $hint = match (true) {
            $from === FrameworkState::Uninitialized => ' Register Gcego\Core\GcegoServiceProvider first.',
            $from === FrameworkState::Booting && $to === FrameworkState::Booting => ' The framework is already booting; do not call boot() from a package or extension boot hook.',
            default => '',
        };

        return new self(sprintf(
            'Invalid GCEGO lifecycle transition from "%s" to "%s".%s',
            $from->value,
            $to->value,
            $hint,
        ));
    }
}
