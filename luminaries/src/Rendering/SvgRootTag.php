<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Rendering;

/**
 * Rewrites the opening <svg> tag of trusted icon markup.
 *
 * Blade Icons prepends attributes, which leaves duplicates when the file
 * already declares them (Heroicons ship aria-hidden="true"). Merging the root
 * tag instead lets a labelled icon drop aria-hidden and lets size classes win
 * over fixed width/height. Only trusted markup (installed collections or
 * validated custom files) is ever passed here.
 */
final class SvgRootTag
{
    /**
     * Root attributes always replaced by Luminaries.
     */
    private const array Managed = ['class', 'width', 'height', 'role', 'focusable', 'aria-hidden', 'aria-label', 'aria-labelledby'];

    /**
     * @param  array<string, string>  $attributes  Name => raw value; values are escaped here.
     */
    public static function merge(string $svg, array $attributes): string
    {
        if (preg_match('/<svg\b([^>]*?)(\/?)>/i', $svg, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        [$tag, $offset] = $match[0];
        $existing = self::parse($match[1][0]);
        $remove = [...self::Managed, ...array_keys($attributes)];
        $merged = [];

        foreach ($existing as $name => $value) {
            if (! in_array(strtolower($name), $remove, true)) {
                $merged[$name] = $value;
            }
        }

        foreach ($attributes as $name => $value) {
            $merged[$name] = e($value);
        }

        $rendered = '<svg';

        foreach ($merged as $name => $value) {
            $rendered .= sprintf(' %s="%s"', $name, $value);
        }

        $rendered .= $match[2][0] === '/' ? '/>' : '>';
        $body = substr($svg, $offset + strlen($tag));

        return substr($svg, 0, $offset).$rendered.(string) preg_replace('/<title\b[^>]*>.*?<\/title>/is', '', $body);
    }

    /**
     * @return array<string, string> Name => already-escaped value (as written in trusted markup).
     */
    private static function parse(string $attributes): array
    {
        preg_match_all('/([A-Za-z_:][A-Za-z0-9_:.\-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?/', $attributes, $matches, PREG_SET_ORDER);
        $parsed = [];

        foreach ($matches as $match) {
            $value = isset($match[3]) ? str_replace('"', '&quot;', $match[3]) : ($match[2] ?? '');
            $parsed[$match[1]] = $value;
        }

        return $parsed;
    }
}
