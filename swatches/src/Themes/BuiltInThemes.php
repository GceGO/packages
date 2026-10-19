<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

/**
 * Optional theme presets. Each extends "gcego", defines light and dark values, and
 * passes every SemanticTokens::ContrastPairs pair (tests/Unit/ThemesTest.php).
 * The active preset is printed at runtime (<x-gcego-swatches::theme>); resources/css/themes.css
 * ships all of them for stylesheets that prefer static CSS (css.static_presets).
 */
final class BuiltInThemes
{
    /**
     * name => [label, description, light, dark]
     *
     * @var array<string, array{string, string, array<string, string>, array<string, string>}>
     */
    public const array Presets = [
        'midnight' => ['GCEGO Midnight', 'Indigo actions; deep navy surfaces in dark mode.', [
            'primary' => '#4338ca',
            'primary-hover' => '#3730a3',
            'primary-soft' => '#eef0fd',
            'chart-1' => '#4338ca',
        ], [
            'background' => '#090b17',
            'foreground' => '#e6e8f5',
            'surface' => '#0f1224',
            'surface-muted' => '#171b34',
            'muted-foreground' => '#a0a6c8',
            'border' => '#242a48',
            'border-strong' => '#6c739a',
            'primary' => '#818cf8',
            'primary-hover' => '#a5b4fc',
            'primary-foreground' => '#0d0b2e',
            'primary-soft' => '#1d1f45',
            'secondary' => '#242a48',
            'secondary-hover' => '#2d3456',
            'chart-1' => '#818cf8',
        ]],
        'slate' => ['GCEGO Slate', 'Neutral slate actions for data-dense back offices.', [
            'primary' => '#334155',
            'primary-hover' => '#1e293b',
            'primary-soft' => '#eef1f5',
            'chart-1' => '#334155',
        ], [
            'primary' => '#cbd5e1',
            'primary-hover' => '#e2e8f0',
            'primary-foreground' => '#0f172a',
            'primary-soft' => '#1f2733',
            'chart-1' => '#cbd5e1',
        ]],
        'ocean' => ['GCEGO Ocean', 'Cyan actions on cool surfaces.', [
            'primary' => '#0e7490',
            'primary-hover' => '#155e75',
            'primary-soft' => '#e6f4f8',
            'background' => '#f4f7f9',
            'chart-1' => '#0e7490',
        ], [
            'primary' => '#22d3ee',
            'primary-hover' => '#67e8f9',
            'primary-foreground' => '#03222b',
            'primary-soft' => '#0b2a33',
            'chart-1' => '#22d3ee',
        ]],
        'forest' => ['GCEGO Forest', 'Emerald actions with warm neutrals.', [
            'primary' => '#047857',
            'primary-hover' => '#065f46',
            'primary-soft' => '#e6f5ef',
            'background' => '#f7f7f4',
            'surface-muted' => '#f2f2ed',
            'border' => '#e4e4dc',
            'chart-1' => '#047857',
        ], [
            'primary' => '#34d399',
            'primary-hover' => '#6ee7b7',
            'primary-foreground' => '#022c22',
            'primary-soft' => '#0c2a20',
            'chart-1' => '#34d399',
        ]],
    ];

    /**
     * @return list<Theme>
     */
    public static function all(): array
    {
        $themes = [];

        foreach (self::Presets as $name => [$label, $description, $light, $dark]) {
            $themes[] = new Theme($name, $light, $dark, Theme::Base, $label, $description, ThemeSource::BuiltIn);
        }

        return $themes;
    }
}
