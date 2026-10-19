<?php

declare(strict_types=1);

namespace Gcego\Chroma\Exceptions;

use InvalidArgumentException;

class InvalidSlotException extends InvalidArgumentException implements ChromaException
{
    public static function invalidName(string $name): self
    {
        return new self(sprintf('"%s" is not a valid slot name. Use lowercase identifiers such as "root", "header" or "toolbar-actions".', $name));
    }

    /**
     * @param  list<string>  $supported
     */
    public static function unknown(string $slot, string $component, array $supported): self
    {
        return new self(sprintf(
            '%s has no "%s" slot. Supported slots: %s.',
            $component,
            $slot,
            $supported === [] ? 'none' : implode(', ', $supported),
        ));
    }

    public static function invalidItem(string $type): self
    {
        return new self(sprintf('Tailwind accepts class strings, lists of classes and Slot objects, %s given.', $type));
    }
}
