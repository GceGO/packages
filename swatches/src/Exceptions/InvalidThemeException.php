<?php

declare(strict_types=1);

namespace Gcego\Swatches\Exceptions;

use Gcego\Swatches\Support\Safe;
use InvalidArgumentException;

class InvalidThemeException extends InvalidArgumentException implements SwatchesException
{
    public static function invalidName(string $name): self
    {
        return new self(sprintf('%s is not a valid theme name. Use a lowercase identifier such as "ocean".', Safe::quote($name)));
    }

    /**
     * @param  list<string>  $known
     */
    public static function unknown(string $name, array $known): self
    {
        return new self(sprintf('Theme %s is not registered. Registered themes: %s.', Safe::quote($name), implode(', ', $known)));
    }

    public static function unknownParent(string $name, string $parent): self
    {
        return new self(sprintf('Theme %s extends %s, which is not registered.', Safe::quote($name), Safe::quote($parent)));
    }

    /**
     * @param  list<string>  $chain
     */
    public static function circular(array $chain): self
    {
        return new self(sprintf('Theme inheritance forms a cycle: %s.', implode(' → ', array_map(Safe::quote(...), $chain))));
    }

    public static function invalidConfig(string $key, string $reason): self
    {
        return new self(sprintf('Invalid configuration gcego-swatches.%s: %s', $key, $reason));
    }

    public static function failsValidation(string $name, string $summary): self
    {
        return new self(sprintf('Theme %s failed validation: %s', Safe::quote($name), $summary));
    }
}
