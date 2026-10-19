<?php

declare(strict_types=1);

namespace Gcego\Swatches\Palettes;

/**
 * Reference palettes, copied verbatim from the theme.css of Tailwind CSS (TailwindVersion).
 *
 * Only these families are mirrored in PHP (for contrast checks, scale previews and
 * theme authoring); the CSS utilities themselves come from Tailwind, which already
 * defines every --color-<family>-<step>. tests/Unit/PalettesTest.php compares these
 * values with the installed tailwindcss package whenever node_modules is present.
 */
final class BuiltInPalettes
{
    public const string TailwindVersion = '4.3.3';

    /**
     * @var array<string, string>
     */
    public const array Labels = ['neutral' => 'Neutral', 'slate' => 'Slate', 'blue' => 'Blue', 'indigo' => 'Indigo', 'violet' => 'Violet', 'emerald' => 'Emerald', 'amber' => 'Amber', 'red' => 'Red', 'teal' => 'Teal', 'cyan' => 'Cyan'];

    /**
     * family => step => OKLCH
     *
     * @var array<string, array<int, string>>
     */
    public const array Scales = [
        'neutral' => [
            50 => 'oklch(98.5% 0 none)',
            100 => 'oklch(97% 0 none)',
            200 => 'oklch(92.2% 0 none)',
            300 => 'oklch(87% 0 none)',
            400 => 'oklch(70.8% 0 none)',
            500 => 'oklch(55.6% 0 none)',
            600 => 'oklch(43.9% 0 none)',
            700 => 'oklch(37.1% 0 none)',
            800 => 'oklch(26.9% 0 none)',
            900 => 'oklch(20.5% 0 none)',
            950 => 'oklch(14.5% 0 none)',
        ],
        'slate' => [
            50 => 'oklch(98.4% 0.003 247.858)',
            100 => 'oklch(96.8% 0.007 247.896)',
            200 => 'oklch(92.9% 0.013 255.508)',
            300 => 'oklch(86.9% 0.022 252.894)',
            400 => 'oklch(70.4% 0.04 256.788)',
            500 => 'oklch(55.4% 0.046 257.417)',
            600 => 'oklch(44.6% 0.043 257.281)',
            700 => 'oklch(37.2% 0.044 257.287)',
            800 => 'oklch(27.9% 0.041 260.031)',
            900 => 'oklch(20.8% 0.042 265.755)',
            950 => 'oklch(12.9% 0.042 264.695)',
        ],
        'blue' => [
            50 => 'oklch(97% 0.014 254.604)',
            100 => 'oklch(93.2% 0.032 255.585)',
            200 => 'oklch(88.2% 0.059 254.128)',
            300 => 'oklch(80.9% 0.105 251.813)',
            400 => 'oklch(70.7% 0.165 254.624)',
            500 => 'oklch(62.3% 0.214 259.815)',
            600 => 'oklch(54.6% 0.245 262.881)',
            700 => 'oklch(48.8% 0.243 264.376)',
            800 => 'oklch(42.4% 0.199 265.638)',
            900 => 'oklch(37.9% 0.146 265.522)',
            950 => 'oklch(28.2% 0.091 267.935)',
        ],
        'indigo' => [
            50 => 'oklch(96.2% 0.018 272.314)',
            100 => 'oklch(93% 0.034 272.788)',
            200 => 'oklch(87% 0.065 274.039)',
            300 => 'oklch(78.5% 0.115 274.713)',
            400 => 'oklch(67.3% 0.182 276.935)',
            500 => 'oklch(58.5% 0.233 277.117)',
            600 => 'oklch(51.1% 0.262 276.966)',
            700 => 'oklch(45.7% 0.24 277.023)',
            800 => 'oklch(39.8% 0.195 277.366)',
            900 => 'oklch(35.9% 0.144 278.697)',
            950 => 'oklch(25.7% 0.09 281.288)',
        ],
        'violet' => [
            50 => 'oklch(96.9% 0.016 293.756)',
            100 => 'oklch(94.3% 0.029 294.588)',
            200 => 'oklch(89.4% 0.057 293.283)',
            300 => 'oklch(81.1% 0.111 293.571)',
            400 => 'oklch(70.2% 0.183 293.541)',
            500 => 'oklch(60.6% 0.25 292.717)',
            600 => 'oklch(54.1% 0.281 293.009)',
            700 => 'oklch(49.1% 0.27 292.581)',
            800 => 'oklch(43.2% 0.232 292.759)',
            900 => 'oklch(38% 0.189 293.745)',
            950 => 'oklch(28.3% 0.141 291.089)',
        ],
        'emerald' => [
            50 => 'oklch(97.9% 0.021 166.113)',
            100 => 'oklch(95% 0.052 163.051)',
            200 => 'oklch(90.5% 0.093 164.15)',
            300 => 'oklch(84.5% 0.143 164.978)',
            400 => 'oklch(76.5% 0.177 163.223)',
            500 => 'oklch(69.6% 0.17 162.48)',
            600 => 'oklch(59.6% 0.145 163.225)',
            700 => 'oklch(50.8% 0.118 165.612)',
            800 => 'oklch(43.2% 0.095 166.913)',
            900 => 'oklch(37.8% 0.077 168.94)',
            950 => 'oklch(26.2% 0.051 172.552)',
        ],
        'amber' => [
            50 => 'oklch(98.7% 0.022 95.277)',
            100 => 'oklch(96.2% 0.059 95.617)',
            200 => 'oklch(92.4% 0.12 95.746)',
            300 => 'oklch(87.9% 0.169 91.605)',
            400 => 'oklch(82.8% 0.189 84.429)',
            500 => 'oklch(76.9% 0.188 70.08)',
            600 => 'oklch(66.6% 0.179 58.318)',
            700 => 'oklch(55.5% 0.163 48.998)',
            800 => 'oklch(47.3% 0.137 46.201)',
            900 => 'oklch(41.4% 0.112 45.904)',
            950 => 'oklch(27.9% 0.077 45.635)',
        ],
        'red' => [
            50 => 'oklch(97.1% 0.013 17.38)',
            100 => 'oklch(93.6% 0.032 17.717)',
            200 => 'oklch(88.5% 0.062 18.334)',
            300 => 'oklch(80.8% 0.114 19.571)',
            400 => 'oklch(70.4% 0.191 22.216)',
            500 => 'oklch(63.7% 0.237 25.331)',
            600 => 'oklch(57.7% 0.245 27.325)',
            700 => 'oklch(50.5% 0.213 27.518)',
            800 => 'oklch(44.4% 0.177 26.899)',
            900 => 'oklch(39.6% 0.141 25.723)',
            950 => 'oklch(25.8% 0.092 26.042)',
        ],
        'teal' => [
            50 => 'oklch(98.4% 0.014 180.72)',
            100 => 'oklch(95.3% 0.051 180.801)',
            200 => 'oklch(91% 0.096 180.426)',
            300 => 'oklch(85.5% 0.138 181.071)',
            400 => 'oklch(77.7% 0.152 181.912)',
            500 => 'oklch(70.4% 0.14 182.503)',
            600 => 'oklch(60% 0.118 184.704)',
            700 => 'oklch(51.1% 0.096 186.391)',
            800 => 'oklch(43.7% 0.078 188.216)',
            900 => 'oklch(38.6% 0.063 188.416)',
            950 => 'oklch(27.7% 0.046 192.524)',
        ],
        'cyan' => [
            50 => 'oklch(98.4% 0.019 200.873)',
            100 => 'oklch(95.6% 0.045 203.388)',
            200 => 'oklch(91.7% 0.08 205.041)',
            300 => 'oklch(86.5% 0.127 207.078)',
            400 => 'oklch(78.9% 0.154 211.53)',
            500 => 'oklch(71.5% 0.143 215.221)',
            600 => 'oklch(60.9% 0.126 221.723)',
            700 => 'oklch(52% 0.105 223.128)',
            800 => 'oklch(45% 0.085 224.283)',
            900 => 'oklch(39.8% 0.07 227.392)',
            950 => 'oklch(30.2% 0.056 229.695)',
        ],
    ];

    /**
     * @return list<Palette>
     */
    public static function all(): array
    {
        $palettes = [];

        foreach (self::Scales as $name => $scale) {
            $palettes[] = Palette::fromStrings($name, $scale, self::Labels[$name], ['source' => 'tailwindcss@'.self::TailwindVersion]);
        }

        return $palettes;
    }
}
