<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Palettes\Palette;
use Gcego\Swatches\Palettes\ScaleGenerator;
use Gcego\Swatches\Themes\ResolvedTheme;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * Turns a Brand into accessible token overrides for a theme. Stateless: the same
 * brand and theme always give the same result, and nothing is kept between calls,
 * so one tenant's brand can never leak into another's request.
 *
 * Primary: a scale is generated from the brand color (ScaleGenerator). In light mode
 * the brand color itself is used when it passes; otherwise the nearest darker step
 * that keeps 4.5:1 for primary text on the surface and background, for its label
 * and on its soft tint. Dark mode picks the nearest lighter step that does the same
 * against the dark surfaces. Hover is the next step in the direction of more contrast.
 *
 * Accent: a soft tint of the brand accent for highlight backgrounds, with the
 * theme's foreground on it (4.5:1).
 *
 * A color that cannot be made to pass is not applied; CompiledBrand::$notes says why.
 */
final readonly class BrandCompiler
{
    private const array LightPrimarySteps = [600, 700, 800, 900, 950];

    private const array DarkPrimarySteps = [400, 300, 200, 500, 100];

    public function __construct(private ScaleGenerator $generator = new ScaleGenerator) {}

    public function compile(Brand $brand, ResolvedTheme $theme): CompiledBrand
    {
        $light = [];
        $dark = [];
        $notes = [];

        if ($brand->primary !== null) {
            $scale = $this->generator->generate('brand', $brand->primary);
            $lightPrimary = $this->lightPrimary($brand->primary, $scale, $theme->light);
            $darkPrimary = $this->darkPrimary($scale, $theme->dark);

            $lightPrimary === null ? $notes[] = 'The primary color cannot reach 4.5:1 in light mode; the theme primary is kept.' : $light += $lightPrimary;
            $darkPrimary === null ? $notes[] = 'The primary color cannot reach 4.5:1 in dark mode; the theme primary is kept.' : $dark += $darkPrimary;

            if ($lightPrimary !== null && ! $brand->primary->equals(Color::parse($lightPrimary['primary']))) {
                $notes[] = sprintf('The primary color was darkened to %s for 4.5:1 contrast in light mode.', $lightPrimary['primary']);
            }
        }

        if ($brand->accent !== null) {
            $scale = $this->generator->generate('accent', $brand->accent);
            $lightAccent = $this->tint($scale, [100, 50, 200], $theme->light);
            $darkAccent = $this->darkTint($brand->accent, $theme->dark);

            $lightAccent === null ? $notes[] = 'The accent color cannot keep text readable in light mode; it is not applied.' : $light += $lightAccent;
            $darkAccent === null ? $notes[] = 'The accent color cannot keep text readable in dark mode; it is not applied.' : $dark += $darkAccent;
        }

        return new CompiledBrand($brand, $theme->name(), new TokenSet($light), new TokenSet($dark), $notes);
    }

    /**
     * @return array<string, string>|null
     */
    private function lightPrimary(Color $brand, Palette $scale, TokenSet $theme): ?array
    {
        $candidates = [$brand, ...array_map(static fn (int $step): Color => $scale->get($step), self::LightPrimarySteps)];
        $soft = $scale->get(50);

        foreach ($candidates as $index => $candidate) {
            $foreground = Color::white();

            if (! $this->readable($candidate, $foreground, $soft, $theme)) {
                continue;
            }

            $hover = $this->darker($candidate, $scale, $index === 0 ? null : self::LightPrimarySteps[$index - 1]);

            if ($hover->equals($candidate)) {
                $hover = $this->lighterReadable($candidate, $scale, $foreground) ?? $hover;
            }

            return [
                'primary' => $candidate->toHex(),
                'primary-hover' => $hover->toHex(),
                'primary-active' => '{primary-hover}',
                'primary-foreground' => $foreground->toHex(),
                'primary-soft' => $soft->toHex(),
                'ring' => '{primary}',
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function darkPrimary(Palette $scale, TokenSet $theme): ?array
    {
        foreach (self::DarkPrimarySteps as $step) {
            $candidate = $scale->get($step);
            [, $chroma, $hue] = $candidate->toOklch();
            $soft = Color::oklch(0.27, min($chroma * 0.35, 0.06), $hue);
            $foreground = $scale->get(950);

            if (Contrast::ratio($foreground, $candidate) < 4.5) {
                $foreground = Color::parse('#04070d');
            }

            if (! $this->readable($candidate, $foreground, $soft, $theme)) {
                continue;
            }

            $lighter = [400 => 300, 300 => 200, 200 => 100, 500 => 400, 100 => 50][$step];
            $hover = Contrast::ratio($foreground, $scale->get($lighter)) >= 4.5 ? $scale->get($lighter) : $candidate;

            return [
                'primary' => $candidate->toHex(),
                'primary-hover' => $hover->toHex(),
                'primary-active' => '{primary-hover}',
                'primary-foreground' => $foreground->toHex(),
                'primary-soft' => $soft->toHex(),
                'ring' => '{primary}',
            ];
        }

        return null;
    }

    /**
     * Primary as text on surfaces and its tint, and its label on it, all at 4.5:1.
     */
    private function readable(Color $primary, Color $foreground, Color $soft, TokenSet $theme): bool
    {
        $backdrop = $theme->resolve('background');

        foreach (['surface', 'background', 'surface-muted'] as $surface) {
            if (Contrast::ratio($primary, $theme->resolve($surface), $backdrop) < 4.5) {
                return false;
            }
        }

        return Contrast::ratio($foreground, $primary) >= 4.5
            && Contrast::ratio($primary, $soft) >= 4.5
            && Contrast::ratio($theme->resolve('foreground'), $soft) >= 4.5;
    }

    private function darker(Color $primary, Palette $scale, ?int $step): Color
    {
        if ($step !== null) {
            $next = [600 => 700, 700 => 800, 800 => 900, 900 => 950, 950 => 950][$step];

            return $scale->get($next);
        }

        $anchor = (int) ($scale->metadata['anchor'] ?? 600);
        $steps = Palette::Steps;
        $index = (int) array_search($anchor, $steps, true);

        return $scale->get($steps[min($index + 1, count($steps) - 1)]);
    }

    /**
     * For the darkest brands (no darker step left): the nearest lighter step that keeps the label readable.
     */
    private function lighterReadable(Color $primary, Palette $scale, Color $foreground): ?Color
    {
        foreach (array_reverse(Palette::Steps) as $step) {
            $color = $scale->get($step);

            if (! $color->equals($primary) && $color->luminance() > $primary->luminance() && Contrast::ratio($foreground, $color) >= 4.5) {
                return $color;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $steps
     * @return array<string, string>|null
     */
    private function tint(Palette $scale, array $steps, TokenSet $theme): ?array
    {
        foreach ($steps as $step) {
            if (Contrast::ratio($theme->resolve('foreground'), $scale->get($step)) >= 4.5) {
                return ['accent' => $scale->get($step)->toHex(), 'accent-foreground' => '{foreground}'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function darkTint(Color $accent, TokenSet $theme): ?array
    {
        [, $chroma, $hue] = $accent->toOklch();

        foreach ([0.3, 0.27, 0.24] as $lightness) {
            $tint = Color::oklch($lightness, min($chroma * 0.4, 0.07), $hue);

            if (Contrast::ratio($theme->resolve('foreground'), $tint) >= 4.5) {
                return ['accent' => $tint->toHex(), 'accent-foreground' => '{foreground}'];
            }
        }

        return null;
    }
}
