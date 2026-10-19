<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

/**
 * The main colors users may pick (GcegoAppearance.setAccent()). Each keeps readable text on
 * the primary color (at least 4.5:1) in light and dark mode; "teal" is GCEGO's own.
 */
final class Accents
{
    /**
     * name => [label, light primary, light hover, light soft, dark primary, dark hover, dark foreground, dark soft]
     *
     * @var array<string, array{string, string, string, string, string, string, string, string}>
     */
    public const array Presets = [
        'teal' => ['Teal', '#0b7570', '#085c58', '#e7f3f2', '#3ec2b5', '#6ad5ca', '#04201e', '#12302d'],
        'blue' => ['Blue', '#1d4ed8', '#1e40af', '#e8effd', '#60a5fa', '#93c5fd', '#06142e', '#12223d'],
        'indigo' => ['Indigo', '#4338ca', '#3730a3', '#eef0fd', '#818cf8', '#a5b4fc', '#0d0b2e', '#1d1c3f'],
        'violet' => ['Violet', '#6d28d9', '#5b21b6', '#f3eefd', '#a78bfa', '#c4b5fd', '#180a33', '#261a40'],
        'rose' => ['Rose', '#be123c', '#9f1239', '#fdecef', '#fb7185', '#fda4af', '#2a0610', '#3a1520'],
        'red' => ['Red', '#b91c1c', '#991b1b', '#fdecec', '#f87171', '#fca5a5', '#2a0707', '#3a1616'],
        'orange' => ['Orange', '#c2410c', '#9a3412', '#fdf0e8', '#fb923c', '#fdba74', '#2a1205', '#3a2214'],
        'amber' => ['Amber', '#b45309', '#92400e', '#fdf4e3', '#fbbf24', '#fcd34d', '#261a03', '#3a2c12'],
        'green' => ['Green', '#15803d', '#166534', '#e8f6ec', '#4ade80', '#86efac', '#05230f', '#13301d'],
    ];

    /**
     * @param  list<string>|null  $only  the preset names to offer, in order (null: all)
     * @return array<string, Accent>
     */
    public static function all(?array $only = null): array
    {
        $accents = [];

        foreach ($only ?? array_keys(self::Presets) as $name) {
            if (! isset(self::Presets[$name])) {
                continue;
            }

            [$label, $primary, $hover, $soft, $darkPrimary, $darkHover, $darkForeground, $darkSoft] = self::Presets[$name];
            $accents[$name] = new Accent(
                $name,
                $label,
                ['primary' => $primary, 'primary-hover' => $hover, 'primary-foreground' => '#ffffff', 'primary-soft' => $soft, 'ring' => $primary],
                ['primary' => $darkPrimary, 'primary-hover' => $darkHover, 'primary-foreground' => $darkForeground, 'primary-soft' => $darkSoft, 'ring' => $darkPrimary],
            );
        }

        return $accents;
    }
}
