<?php

declare(strict_types=1);

namespace Gcego\Panels\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The signed-in user may not enter the panel (403).
 */
final class PanelAccessDeniedException extends AccessDeniedHttpException implements PanelException
{
    public static function for(string $panel): self
    {
        return new self(sprintf('You may not access the "%s" panel.', $panel));
    }
}
