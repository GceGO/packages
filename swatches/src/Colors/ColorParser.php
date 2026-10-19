<?php

declare(strict_types=1);

namespace Gcego\Swatches\Colors;

use Gcego\Swatches\Exceptions\InvalidColorException;

/**
 * Parses CSS color syntax into a Color.
 *
 * Accepted: #rgb, #rgba, #rrggbb, #rrggbbaa; rgb()/rgba() and hsl()/hsla() in comma and
 * space syntax; oklch(); oklab(); the CSS named colors and "transparent". Channels clamp
 * to their CSS ranges like browsers do. Anything else is rejected, including var(),
 * currentcolor, calc() and relative color syntax: those cannot be evaluated on the server.
 */
final class ColorParser
{
    public const int MaxLength = 100;

    private const string Number = '[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?';

    /**
     * @throws InvalidColorException
     */
    public static function parse(string $value): Color
    {
        $input = strtolower(trim($value, " \t\n\r"));

        if ($input === '' || strlen($input) > self::MaxLength || preg_match('/[\x00-\x1f\x7f]/', $input) === 1) {
            throw InvalidColorException::unparsable($value);
        }

        if (str_starts_with($input, 'var(') || str_starts_with($input, '{')) {
            throw InvalidColorException::unresolved($value);
        }

        if ($input === 'transparent') {
            return Color::make(ColorSpace::Srgb, [0.0, 0.0, 0.0], 0.0, ColorFormat::Named, 'transparent');
        }

        if (($hex = NamedColors::hex($input)) !== null) {
            return Color::make(ColorSpace::Srgb, self::hexChannels(substr($hex, 1))[0], 1.0, ColorFormat::Named, $input);
        }

        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $input, $match) === 1) {
            [$channels, $alpha] = self::hexChannels($match[1]);

            return Color::make(ColorSpace::Srgb, $channels, $alpha, ColorFormat::Hex);
        }

        if (preg_match('/^(rgba?|hsla?|oklch|oklab)\(\s*([^()]*?)\s*\)$/', $input, $match) === 1) {
            return self::function($match[1], $match[2], $value);
        }

        throw InvalidColorException::unparsable($value);
    }

    /**
     * @return array{array{float, float, float}, float}
     */
    private static function hexChannels(string $hex): array
    {
        if (strlen($hex) <= 4) {
            $hex = implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($hex)));
        }

        $bytes = array_map(static fn (string $pair): float => hexdec($pair) / 255, str_split($hex, 2));

        return [[$bytes[0], $bytes[1], $bytes[2]], $bytes[3] ?? 1.0];
    }

    private static function function(string $name, string $arguments, string $original): Color
    {
        $legacy = str_contains($arguments, ',');

        if ($legacy) {
            if (! in_array($name, ['rgb', 'rgba', 'hsl', 'hsla'], true) || str_contains($arguments, '/')) {
                throw InvalidColorException::unparsable($original);
            }

            $parts = array_map('trim', explode(',', $arguments));
            $channels = array_slice($parts, 0, 3);
            $alpha = $parts[3] ?? null;

            if (count($parts) < 3 || count($parts) > 4) {
                throw InvalidColorException::unparsable($original);
            }
        } else {
            $sections = explode('/', $arguments);

            if (count($sections) > 2) {
                throw InvalidColorException::unparsable($original);
            }

            $channels = preg_split('/\s+/', trim($sections[0])) ?: [];
            $alpha = isset($sections[1]) ? trim($sections[1]) : null;

            if (count($channels) !== 3 || $alpha === '') {
                throw InvalidColorException::unparsable($original);
            }
        }

        $alphaValue = $alpha === null ? 1.0 : self::clamp(self::numberOrPercent($alpha, 1.0, $original), 0, 1);
        [$first, $second, $third] = $channels;

        return match ($name) {
            'rgb', 'rgba' => Color::make(ColorSpace::Srgb, [
                self::clamp(self::numberOrPercent($first, 255.0, $original) / 255, 0, 1),
                self::clamp(self::numberOrPercent($second, 255.0, $original) / 255, 0, 1),
                self::clamp(self::numberOrPercent($third, 255.0, $original) / 255, 0, 1),
            ], $alphaValue, ColorFormat::Rgb),
            'hsl', 'hsla' => Color::make(ColorSpace::Hsl, [
                self::angle($first, $original),
                self::clamp(self::percentage($second, $legacy, $original), 0, 1),
                self::clamp(self::percentage($third, $legacy, $original), 0, 1),
            ], $alphaValue, ColorFormat::Hsl),
            'oklch' => Color::make(ColorSpace::Oklch, [
                self::clamp(self::numberOrPercent($first, 1.0, $original), 0, 1),
                self::clamp(self::numberOrPercent($second, 0.4, $original), 0, 1),
                self::angle($third, $original),
            ], $alphaValue, ColorFormat::Oklch),
            default => Color::make(ColorSpace::Oklab, [
                self::clamp(self::numberOrPercent($first, 1.0, $original), 0, 1),
                self::clamp(self::numberOrPercent($second, 0.4, $original), -1, 1),
                self::clamp(self::numberOrPercent($third, 0.4, $original), -1, 1),
            ], $alphaValue, ColorFormat::Oklab),
        };
    }

    /**
     * A number, or a percentage of $percentReference ("none" is 0).
     */
    private static function numberOrPercent(string $token, float $percentReference, string $original): float
    {
        if ($token === 'none') {
            return 0.0;
        }

        if (preg_match('/^('.self::Number.')(%)?$/', $token, $match) !== 1) {
            throw InvalidColorException::unparsable($original);
        }

        $number = self::finite((float) $match[1], $original);

        return isset($match[2]) ? $number / 100 * $percentReference : $number;
    }

    /**
     * HSL saturation or lightness as 0–1. The comma syntax requires "%"; the space syntax also takes 0–100 numbers.
     */
    private static function percentage(string $token, bool $legacy, string $original): float
    {
        if ($token === 'none') {
            return 0.0;
        }

        if (preg_match('/^('.self::Number.')(%)?$/', $token, $match) !== 1 || ($legacy && ! isset($match[2]))) {
            throw InvalidColorException::unparsable($original);
        }

        return self::finite((float) $match[1], $original) / 100;
    }

    private static function angle(string $token, string $original): float
    {
        if ($token === 'none') {
            return 0.0;
        }

        if (preg_match('/^('.self::Number.')(deg|rad|grad|turn)?$/', $token, $match) !== 1) {
            throw InvalidColorException::unparsable($original);
        }

        $number = self::finite((float) $match[1], $original);

        return Conversions::normalizeHue(match ($match[2] ?? 'deg') {
            'rad' => rad2deg($number),
            'grad' => $number * 0.9,
            'turn' => $number * 360,
            default => $number,
        });
    }

    private static function finite(float $number, string $original): float
    {
        if (! is_finite($number) || abs($number) > 1.0e6) {
            throw InvalidColorException::unparsable($original);
        }

        return $number;
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
