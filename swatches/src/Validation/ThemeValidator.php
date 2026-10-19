<?php

declare(strict_types=1);

namespace Gcego\Swatches\Validation;

use Gcego\Swatches\Accessibility\Apca;
use Gcego\Swatches\Accessibility\ColorVision;
use Gcego\Swatches\Accessibility\ColorVisionSimulator;
use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Accessibility\ContrastAuditor;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Themes\ThemeRegistry;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * Validates registered themes before they are used.
 *
 * Errors: unknown parents, circular inheritance, broken or circular token
 * references, and WCAG 2.2 AA contrast failures. Warnings: literal colors
 * outside sRGB (they are gamut mapped) and chart series too similar to tell
 * apart (ΔE OK < 0.05). Values themselves are validated when a Theme is built,
 * so an invalid color or unknown token never reaches the registry.
 *
 * Advisory checks, off unless asked for (they never fail validation):
 * - APCA: pairs below the APCA level mapped to their requirement (Apca::minimum());
 * - color vision: chart series or status colors that collapse (ΔE OK < 0.03) under
 *   simulated protanopia, deuteranopia, tritanopia or achromatopsia.
 */
final readonly class ThemeValidator
{
    public const float MinimumSeriesDistance = 0.05;

    public const float MinimumSimulatedDistance = 0.03;

    public function __construct(
        private ThemeRegistry $themes,
        private ContrastAuditor $auditor = new ContrastAuditor,
        private bool $apca = false,
        private bool $colorVision = false,
    ) {}

    /**
     * @param  bool  $contrast  false checks structure only (inheritance and references)
     */
    public function validate(string $name, bool $contrast = true): ValidationReport
    {
        try {
            $resolved = $this->themes->resolve($name);
        } catch (InvalidThemeException $exception) {
            return new ValidationReport([new Issue(Severity::Error, Issue::Inheritance, $name, $exception->getMessage())], themes: [$name]);
        }

        $issues = [];

        foreach (['light' => $resolved->light, 'dark' => $resolved->dark] as $mode => $tokens) {
            $issues = [...$issues, ...$this->references($name, $mode, $tokens)];
        }

        if ($issues !== [] || ! $contrast) {
            return new ValidationReport($issues, themes: [$name]);
        }

        $contrast = $this->auditor->audit($resolved);

        foreach ($contrast as $result) {
            if (! $result->passes()) {
                $issues[] = new Issue(
                    Severity::Error,
                    Issue::Contrast,
                    $name,
                    sprintf('%s on %s is %s; %s needs %s (%s).', $result->foreground, $result->background, Contrast::format($result->ratio), $result->purpose, $result->requirement->label(), $result->mode()),
                    $result->mode(),
                    $result->foreground,
                    $result->suggestion === null ? null : sprintf('Set %s to %s in %s mode.', $result->foreground, $result->suggestion->toHex(), $result->mode()),
                );
            }
        }

        foreach (['light' => $resolved->light, 'dark' => $resolved->dark] as $mode => $tokens) {
            $issues = [...$issues, ...$this->gamut($name, $mode, $tokens), ...$this->series($name, $mode, $tokens)];

            if ($this->colorVision) {
                $issues = [...$issues, ...$this->colorVision($name, $mode, $tokens)];
            }
        }

        if ($this->apca) {
            foreach ($contrast as $result) {
                if ($result->passes() && ! $result->passesApca()) {
                    $issues[] = new Issue(
                        Severity::Warning,
                        Issue::Apca,
                        $name,
                        sprintf('%s on %s is %s, below APCA %s for %s (WCAG 2.2: %s, passes).', $result->foreground, $result->background, Apca::format($result->apca), Apca::format(Apca::minimum($result->requirement)), $result->purpose, Contrast::format($result->ratio)),
                        $result->mode(),
                        $result->foreground,
                    );
                }
            }
        }

        return new ValidationReport($issues, $contrast, [$name]);
    }

    public function validateAll(): ValidationReport
    {
        $report = new ValidationReport;

        foreach ($this->themes->names() as $name) {
            $report = $report->merge($this->validate($name));
        }

        return $report;
    }

    /**
     * @return list<Issue>
     */
    private function references(string $theme, string $mode, TokenSet $tokens): array
    {
        $issues = [];

        foreach (SemanticTokens::names() as $token) {
            try {
                $tokens->resolve($token);
            } catch (InvalidTokenException $exception) {
                $issues[] = new Issue(Severity::Error, str_contains($exception->getMessage(), 'cycle') ? Issue::CircularReference : Issue::BrokenReference, $theme, $exception->getMessage(), $mode, $token);
            }
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    private function colorVision(string $theme, string $mode, TokenSet $tokens): array
    {
        $groups = [
            'chart series' => array_combine(SemanticTokens::ChartSeries, array_map($tokens->resolve(...), SemanticTokens::ChartSeries)),
            'status colors' => array_combine(SemanticTokens::StatusColors, array_map($tokens->resolve(...), SemanticTokens::StatusColors)),
        ];
        $issues = [];

        foreach (ColorVision::cases() as $vision) {
            foreach ($groups as $group => $colors) {
                $closest = ColorVisionSimulator::closestPair($colors, $vision);

                if ($closest['pair'] !== null && $closest['distance'] < self::MinimumSimulatedDistance) {
                    [$first, $second] = $closest['pair'];
                    $issues[] = new Issue(
                        Severity::Warning,
                        Issue::ColorVision,
                        $theme,
                        sprintf('With %s, %s and %s look alike (ΔE OK %.3f): never rely on color alone for %s; add labels, icons or patterns.', strtolower($vision->label()), $first, $second, $closest['distance'], $group),
                        $mode,
                        $second,
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    private function gamut(string $theme, string $mode, TokenSet $tokens): array
    {
        $issues = [];

        foreach ($tokens as $token => $value) {
            if ($value->color !== null && ! $value->color->inSrgbGamut()) {
                $issues[] = new Issue(Severity::Warning, Issue::OutOfGamut, $theme, sprintf('%s is outside sRGB; sRGB displays show %s.', $token, $value->color->toHex()), $mode, $token);
            }
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    private function series(string $theme, string $mode, TokenSet $tokens): array
    {
        $issues = [];
        $series = SemanticTokens::ChartSeries;

        foreach ($series as $i => $first) {
            foreach (array_slice($series, $i + 1) as $second) {
                $distance = $tokens->resolve($first)->deltaE($tokens->resolve($second));

                if ($distance < self::MinimumSeriesDistance) {
                    $issues[] = new Issue(Severity::Warning, Issue::IndistinctSeries, $theme, sprintf('%s and %s are hard to tell apart (ΔE OK %.3f); pair series with labels or markers.', $first, $second, $distance), $mode, $second);
                }
            }
        }

        return $issues;
    }
}
