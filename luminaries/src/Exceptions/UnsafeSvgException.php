<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Exceptions;

/**
 * A custom SVG file, collection directory or rendering attribute violates the
 * trusted-source policy and was refused.
 */
final class UnsafeSvgException extends LuminariesException
{
    /**
     * @param  list<string>  $violations
     */
    public static function file(string $icon, string $path, array $violations): self
    {
        return new self(self::header().sprintf(
            "Icon %s was rejected by the trusted SVG policy.\n\nFile: %s\n\nViolations:\n- %s",
            $icon,
            $path,
            implode("\n- ", $violations),
        ));
    }

    public static function directory(string $collection, string $path, string $reason): self
    {
        return new self(self::header().sprintf('Custom icon collection "%s" was refused: %s (%s).', $collection, $reason, $path));
    }

    public static function attribute(string $attribute): self
    {
        return new self(self::header().sprintf(
            'The attribute "%s" is not allowed on icons. Allowed: id, class, width, height, fill, stroke, opacity, aria-*, data-*, x-* (Alpine) and wire:* (Livewire).',
            mb_substr($attribute, 0, 64),
        ));
    }
}
