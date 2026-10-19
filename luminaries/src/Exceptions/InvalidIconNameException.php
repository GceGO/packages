<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Exceptions;

/**
 * An icon identifier does not follow the Luminaries naming grammar.
 */
final class InvalidIconNameException extends LuminariesException
{
    public static function for(string $name): self
    {
        return new self(self::header().sprintf(
            "Invalid icon identifier: %s\n\nUse \"provider:icon\" (lucide:pencil), a semantic alias (actions.edit) or a bare name (pencil-square): lowercase letters, digits and dashes.",
            json_encode(mb_substr($name, 0, 120), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""',
        ));
    }
}
