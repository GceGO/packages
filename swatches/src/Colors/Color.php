<?php

declare(strict_types=1);

namespace Gcego\Swatches\Colors;

use Gcego\Swatches\Exceptions\InvalidColorException;
use Stringable;

/**
 * An immutable color: coordinates in the space it was written in, an alpha and its CSS syntax.
 *
 *     Color::parse('#2563eb')->toOklch();          // [0.546, 0.215, 262.88]
 *     Color::parse('oklch(62% 0.19 259)')->toHex(); // gamut mapped when outside sRGB
 *     Color::oklch(0.7, 0.15, 160)->withAlpha(0.5)->toCss();
 *
 * Serialization never echoes input: toCss() rebuilds the value from the parsed
 * numbers, so a Color is always safe to place in a CSS declaration.
 */
final readonly class Color implements Stringable
{
    /**
     * @param  array{float, float, float}  $coords
     */
    private function __construct(
        public ColorSpace $space,
        public array $coords,
        public float $alpha,
        public ColorFormat $format,
        public ?string $name = null,
    ) {}

    /**
     * @throws InvalidColorException
     */
    public static function parse(string $value): self
    {
        return ColorParser::parse($value);
    }

    public static function tryParse(string $value): ?self
    {
        try {
            return ColorParser::parse($value);
        } catch (InvalidColorException) {
            return null;
        }
    }

    /**
     * @param  array{float, float, float}  $coords
     *
     * @internal Use parse() or the named constructors; ColorParser validates ranges first.
     */
    public static function make(ColorSpace $space, array $coords, float $alpha = 1.0, ?ColorFormat $format = null, ?string $name = null): self
    {
        $format ??= match ($space) {
            ColorSpace::Srgb => ColorFormat::Hex,
            ColorSpace::Hsl => ColorFormat::Hsl,
            ColorSpace::Oklab => ColorFormat::Oklab,
            ColorSpace::Oklch => ColorFormat::Oklch,
        };

        if ($space === ColorSpace::Oklch || $space === ColorSpace::Hsl) {
            $coords[$space === ColorSpace::Hsl ? 0 : 2] = Conversions::normalizeHue($coords[$space === ColorSpace::Hsl ? 0 : 2]);
        }

        return new self($space, $coords, max(0.0, min(1.0, $alpha)), $format, $name);
    }

    /**
     * @param  float  $red  0–255
     * @param  float  $green  0–255
     * @param  float  $blue  0–255
     */
    public static function rgb(float $red, float $green, float $blue, float $alpha = 1.0): self
    {
        return self::make(ColorSpace::Srgb, [self::unit($red / 255), self::unit($green / 255), self::unit($blue / 255)], $alpha, ColorFormat::Rgb);
    }

    public static function oklch(float $lightness, float $chroma, float $hue, float $alpha = 1.0): self
    {
        return self::make(ColorSpace::Oklch, [self::unit($lightness), max(0.0, $chroma), $hue], $alpha);
    }

    /**
     * Gamma-encoded sRGB channels 0–1. Colors outside sRGB are gamut mapped (CSS Color 4).
     *
     * @return array{float, float, float}
     */
    public function toSrgb(): array
    {
        return match ($this->space) {
            ColorSpace::Srgb => Gamut::clip($this->coords),
            ColorSpace::Hsl => Gamut::clip(Conversions::hslToSrgb($this->coords)),
            ColorSpace::Oklab => Gamut::mapOklch(Conversions::oklabToOklch($this->coords)),
            ColorSpace::Oklch => Gamut::mapOklch($this->coords),
        };
    }

    /**
     * @return array{float, float, float} L, a, b
     */
    public function toOklab(): array
    {
        return match ($this->space) {
            ColorSpace::Oklab => $this->coords,
            ColorSpace::Oklch => Conversions::oklchToOklab($this->coords),
            ColorSpace::Srgb => Conversions::srgbToOklab($this->coords),
            ColorSpace::Hsl => Conversions::srgbToOklab(Conversions::hslToSrgb($this->coords)),
        };
    }

    /**
     * @return array{float, float, float} L 0–1, C, h degrees
     */
    public function toOklch(): array
    {
        return $this->space === ColorSpace::Oklch ? $this->coords : Conversions::oklabToOklch($this->toOklab());
    }

    /**
     * @return array{float, float, float} h degrees, s and l 0–1
     */
    public function toHsl(): array
    {
        return $this->space === ColorSpace::Hsl ? $this->coords : Conversions::srgbToHsl($this->toSrgb());
    }

    /**
     * Whether the color is displayable in sRGB without gamut mapping.
     */
    public function inSrgbGamut(): bool
    {
        return match ($this->space) {
            ColorSpace::Srgb, ColorSpace::Hsl => true,
            ColorSpace::Oklab => Gamut::inSrgb(Conversions::oklabToSrgb($this->coords)),
            ColorSpace::Oklch => Gamut::inSrgb(Conversions::oklabToSrgb(Conversions::oklchToOklab($this->coords))),
        };
    }

    public function isOpaque(): bool
    {
        return $this->alpha >= 1.0;
    }

    public function withAlpha(float $alpha): self
    {
        return new self($this->space, $this->coords, max(0.0, min(1.0, $alpha)), $this->format === ColorFormat::Named ? ColorFormat::Hex : $this->format);
    }

    /**
     * The same color written in another syntax (coordinates convert; nothing else changes).
     */
    public function as(ColorFormat $format): self
    {
        return match ($format) {
            ColorFormat::Hex, ColorFormat::Rgb => new self(ColorSpace::Srgb, $this->toSrgb(), $this->alpha, $format),
            ColorFormat::Hsl => new self(ColorSpace::Hsl, $this->toHsl(), $this->alpha, $format),
            ColorFormat::Oklch => new self(ColorSpace::Oklch, $this->toOklch(), $this->alpha, $format),
            ColorFormat::Oklab => new self(ColorSpace::Oklab, $this->toOklab(), $this->alpha, $format),
            ColorFormat::Named => $this->name !== null ? $this : new self(ColorSpace::Srgb, $this->toSrgb(), $this->alpha, ColorFormat::Hex),
        };
    }

    /**
     * This color painted over an opaque backdrop (source-over), as an opaque sRGB color.
     */
    public function over(self $backdrop): self
    {
        $base = $backdrop->isOpaque() ? $backdrop->toSrgb() : $backdrop->over(self::white())->toSrgb();

        if ($this->isOpaque()) {
            return new self(ColorSpace::Srgb, $this->toSrgb(), 1.0, ColorFormat::Hex);
        }

        $top = $this->toSrgb();
        $alpha = $this->alpha;
        $mix = static fn (int $i): float => $top[$i] * $alpha + $base[$i] * (1 - $alpha);

        return new self(ColorSpace::Srgb, [$mix(0), $mix(1), $mix(2)], 1.0, ColorFormat::Hex);
    }

    /**
     * WCAG 2.x relative luminance of the (opaque, gamut-mapped) sRGB color.
     */
    public function luminance(): float
    {
        [$r, $g, $b] = Conversions::srgbToLinear($this->toSrgb());

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Perceptual difference ΔE OK (Euclidean distance in OKLab); about 0.02 is just noticeable.
     */
    public function deltaE(self $other): float
    {
        $a = $this->toOklab();
        $b = $other->toOklab();

        return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
    }

    /**
     * Same displayed color: identical 8-bit sRGB channels and alpha (to 1/255).
     */
    public function equals(self $other): bool
    {
        return $this->toHex() === $other->toHex();
    }

    /**
     * #rrggbb, or #rrggbbaa when translucent.
     */
    public function toHex(): string
    {
        [$r, $g, $b] = $this->toSrgb();
        $hex = sprintf('#%02x%02x%02x', self::byte($r), self::byte($g), self::byte($b));

        return $this->isOpaque() ? $hex : $hex.sprintf('%02x', self::byte($this->alpha));
    }

    public function toRgbString(): string
    {
        [$r, $g, $b] = $this->toSrgb();

        return sprintf('rgb(%s %s %s%s)', self::number($r * 255, 2), self::number($g * 255, 2), self::number($b * 255, 2), $this->alphaSuffix());
    }

    public function toHslString(): string
    {
        [$h, $s, $l] = $this->toHsl();

        return sprintf('hsl(%s %s%% %s%%%s)', self::number($h, 2), self::number($s * 100, 2), self::number($l * 100, 2), $this->alphaSuffix());
    }

    /**
     * oklch(62.3% 0.214 259.815): the notation Tailwind CSS v4 uses.
     */
    public function toOklchString(): string
    {
        [$l, $c, $h] = $this->toOklch();

        return sprintf('oklch(%s%% %s %s%s)', self::number($l * 100, 2), self::number($c, 4), self::number($c < 1.0e-6 ? 0.0 : $h, 3), $this->alphaSuffix());
    }

    public function toOklabString(): string
    {
        [$l, $a, $b] = $this->toOklab();

        return sprintf('oklab(%s%% %s %s%s)', self::number($l * 100, 2), self::number($a, 4), self::number($b, 4), $this->alphaSuffix());
    }

    /**
     * The color in its own syntax, rebuilt from parsed numbers (safe in CSS).
     */
    public function toCss(): string
    {
        return match ($this->format) {
            ColorFormat::Hex => $this->toHex(),
            ColorFormat::Rgb => $this->toRgbString(),
            ColorFormat::Hsl => $this->toHslString(),
            ColorFormat::Oklch => $this->toOklchString(),
            ColorFormat::Oklab => $this->toOklabString(),
            ColorFormat::Named => $this->name ?? $this->toHex(),
        };
    }

    public function __toString(): string
    {
        return $this->toCss();
    }

    public static function white(): self
    {
        return new self(ColorSpace::Srgb, [1.0, 1.0, 1.0], 1.0, ColorFormat::Hex);
    }

    public static function black(): self
    {
        return new self(ColorSpace::Srgb, [0.0, 0.0, 0.0], 1.0, ColorFormat::Hex);
    }

    private function alphaSuffix(): string
    {
        return $this->isOpaque() ? '' : ' / '.self::number($this->alpha, 3);
    }

    private static function byte(float $unit): int
    {
        return (int) round(max(0.0, min(1.0, $unit)) * 255);
    }

    private static function unit(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    private static function number(float $value, int $precision): string
    {
        $rounded = round($value, $precision);
        $formatted = rtrim(rtrim(number_format($rounded, $precision, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
