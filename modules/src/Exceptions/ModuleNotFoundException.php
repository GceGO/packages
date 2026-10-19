<?php

declare(strict_types=1);

namespace Gcego\Modules\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No registered module, resource or relation has this name (404: names are never trusted input).
 */
final class ModuleNotFoundException extends NotFoundHttpException implements ModuleException
{
    public static function named(string $kind, string $name): self
    {
        return new self(sprintf('No %s named "%s" is registered.', $kind, mb_substr($name, 0, 120)));
    }
}
