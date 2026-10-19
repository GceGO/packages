<?php

declare(strict_types=1);

namespace Gcego\Studio\Exceptions;

use LogicException;

final class ExtensionException extends LogicException implements StudioException
{
    public static function invalid(string $extension, string $reason): self
    {
        return new self(sprintf('Studio extension "%s" %s', $extension, $reason));
    }
}
