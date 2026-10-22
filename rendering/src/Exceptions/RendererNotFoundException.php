<?php

declare(strict_types=1);

namespace Gcego\Rendering\Exceptions;

use LogicException;

class RendererNotFoundException extends LogicException implements RenderingException
{
    public static function forComponent(string $class, string $type, string $path): self
    {
        return new self(sprintf(
            'No renderer is registered for %s (type "%s") at "%s". Register one with RendererRegistry::register(%s::class, YourRenderer::class).',
            $class,
            $type,
            $path,
            $class,
        ));
    }
}
