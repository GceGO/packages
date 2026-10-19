<?php

declare(strict_types=1);

namespace Gcego\Swatches\Exceptions;

use Gcego\Swatches\Support\Safe;
use InvalidArgumentException;

class InvalidTokenException extends InvalidArgumentException implements SwatchesException
{
    public static function unknown(string $name): self
    {
        return new self(sprintf('%s is not a GCEGO color token. Run "php artisan gcego:swatches:list tokens" for the catalog.', Safe::quote($name)));
    }

    public static function invalidValue(string $name): self
    {
        return new self(sprintf('The value of color token %s is not a color or a {token} reference.', Safe::quote($name)));
    }

    public static function brokenReference(string $name, string $target): self
    {
        return new self(sprintf('Color token %s refers to %s, which does not exist.', Safe::quote($name), Safe::quote($target)));
    }

    /**
     * @param  list<string>  $chain
     */
    public static function circular(array $chain): self
    {
        return new self(sprintf('Color token references form a cycle: %s.', implode(' → ', array_map(Safe::quote(...), $chain))));
    }
}
