<?php

declare(strict_types=1);

namespace Gcego\Rendering\Exceptions;

use InvalidArgumentException;

class InvalidRenderContextException extends InvalidArgumentException implements RenderingException
{
    public static function invalid(string $field, string $reason): self
    {
        return new self(sprintf('Invalid render context %s: %s', $field, $reason));
    }

    public static function notLivewire(string $path): self
    {
        return new self(sprintf('Component at "%s" asked for Livewire state outside a Livewire host. Check $context->isLivewire() first.', $path));
    }
}
