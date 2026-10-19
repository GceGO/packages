<?php

declare(strict_types=1);

namespace Gcego\Studio\Exceptions;

use RuntimeException;

final class IncompatiblePackageException extends RuntimeException implements StudioException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(array $problems): self
    {
        return new self("GCEGO packages are missing or incompatible:\n- ".implode("\n- ", $problems));
    }
}
