<?php

declare(strict_types=1);

namespace Gcego\Panels\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No registered panel has this name (404: names are never trusted input).
 */
final class PanelNotFoundException extends NotFoundHttpException implements PanelException
{
    public static function named(string $name): self
    {
        return new self(sprintf('No panel named "%s" is registered.', mb_substr($name, 0, 80)));
    }
}
