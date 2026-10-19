<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Security;

use Gcego\Luminaries\Exceptions\UnsafeSvgException;

/**
 * The attributes callers may add to a rendered icon.
 *
 * Event handlers, style, href and anything that could load or run code are
 * refused, as are the accessibility attributes Luminaries manages itself
 * (use the label and decorative options instead). Values are always
 * HTML-escaped when rendered.
 */
final class AttributeGuard
{
    private const string Allowed = '/^(?:id|width|height|fill|stroke|opacity|aria-(?!hidden$|label$|labelledby$)[a-z]+|data-[a-z0-9][a-z0-9\-_.]*|x-[a-z][a-z0-9\-_.:]*|wire:[a-z][a-z0-9\-_.:]*)$/';

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, string> Attribute name => raw (unescaped) value.
     *
     * @throws UnsafeSvgException
     */
    public static function filter(array $attributes): array
    {
        $safe = [];

        foreach ($attributes as $name => $value) {
            $name = strtolower((string) $name);

            if (preg_match(self::Allowed, $name) !== 1) {
                throw UnsafeSvgException::attribute($name);
            }

            if ($value === null || $value === false) {
                continue;
            }

            if (! is_scalar($value)) {
                throw UnsafeSvgException::attribute($name);
            }

            $safe[$name] = $value === true ? '' : (string) $value;
        }

        return $safe;
    }
}
