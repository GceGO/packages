<?php

declare(strict_types=1);

namespace Gcego\Testing\Exceptions;

use LogicException;

final class MissingPackageException extends LogicException implements TestingException
{
    public static function for(string $package, string $feature): self
    {
        return new self(sprintf('%s needs %s. Install it with "composer require --dev %s".', $feature, $package, $package));
    }
}
