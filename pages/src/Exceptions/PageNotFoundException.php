<?php

declare(strict_types=1);

namespace Gcego\Pages\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An identity that is not a registered page (404). The message never repeats
 * the requested identity, which may come from a client.
 */
final class PageNotFoundException extends NotFoundHttpException implements PageException
{
    public static function make(): self
    {
        return new self('Page not found.');
    }
}
