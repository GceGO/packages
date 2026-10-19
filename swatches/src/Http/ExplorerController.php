<?php

declare(strict_types=1);

namespace Gcego\Swatches\Http;

use Gcego\Swatches\Accessibility\ColorVision;
use Gcego\Swatches\Accessibility\ColorVisionSimulator;
use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Accessibility\ContrastAuditor;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Support\PreviewClasses;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\SwatchesServiceProvider;
use Gcego\Swatches\Tokens\SemanticTokens;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The developer color explorer (GET gcego-swatches.explorer.path).
 *
 * Read-only: it browses palettes, tokens and themes, previews components, checks
 * contrast and exports theme definitions. It never stores anything. Off unless
 * gcego-swatches.explorer.enabled, and every request must pass the
 * "viewSwatchesExplorer" gate.
 */
final class ExplorerController
{
    public const string Ability = 'viewSwatchesExplorer';

    public function __invoke(Request $request, SwatchesManager $swatches, PreviewClasses $previews): View
    {
        abort_unless(Gate::allows(self::Ability), 403);

        $requested = (string) $request->query('theme', '');
        $theme = $swatches->resolve($swatches->hasTheme($requested) ? $requested : null);

        $check = null;
        $foreground = mb_substr(trim((string) $request->query('fg', '')), 0, 100);
        $background = mb_substr(trim((string) $request->query('bg', '')), 0, 100);

        if ($foreground !== '' && $background !== '') {
            $color = static fn (string $value, bool $dark): ?Color => SemanticTokens::has($value) ? $theme->color($value, $dark) : Color::tryParse($value);
            $check = ['foreground' => $foreground, 'background' => $background, 'results' => []];

            foreach ([false, true] as $dark) {
                $front = $color($foreground, $dark);
                $behind = $color($background, $dark);

                $check['results'][$dark ? 'dark' : 'light'] = $front === null || $behind === null ? null : [
                    'foreground' => $front->toHex(),
                    'background' => $behind->toHex(),
                    'ratio' => Contrast::format(Contrast::ratio($front, $behind, $theme->color('background', $dark))),
                    'text' => Contrast::ratio($front, $behind, $theme->color('background', $dark)) >= 4.5,
                    'large' => Contrast::ratio($front, $behind, $theme->color('background', $dark)) >= 3.0,
                ];
            }
        }

        $groups = [];

        foreach (SemanticTokens::all() as $name => $token) {
            $groups[$token->group->value][] = [
                'name' => $name,
                'description' => $token->description,
                'light' => $theme->light->get($name)?->toString() ?? '',
                'dark' => $theme->dark->get($name)?->toString() ?? '',
                'light_hex' => $theme->color($name)->toHex(),
                'dark_hex' => $theme->color($name, true)->toHex(),
            ];
        }

        $vision = [];

        foreach ([false, true] as $dark) {
            foreach ([null, ...ColorVision::cases()] as $deficiency) {
                $row = [];

                foreach ([...SemanticTokens::StatusColors, ...SemanticTokens::ChartSeries] as $token) {
                    $color = $theme->color($token, $dark)->over($theme->color('surface', $dark));
                    $row[$token] = ($deficiency === null ? $color : ColorVisionSimulator::simulate($color, $deficiency))->toHex();
                }

                $vision[$dark ? 'dark' : 'light'][$deficiency === null ? 'Typical vision' : $deficiency->label()] = $row;
            }
        }

        $audit = (new ContrastAuditor)->audit($theme);
        usort($audit, static fn ($a, $b): int => [$a->passes(), $a->ratio] <=> [$b->passes(), $b->ratio]);

        return view('gcego-swatches::explorer.index', [
            'swatches' => $swatches,
            'theme' => $theme,
            'themes' => $swatches->themes(),
            'palettes' => $swatches->palettes(),
            'groups' => $groups,
            'audit' => $audit,
            'vision' => $vision,
            'check' => $check,
            'previews' => $previews->all(),
            'export' => [
                'json' => (string) json_encode($theme->theme->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'php' => var_export(['label' => $theme->theme->label(), 'extends' => $theme->theme->extends, 'light' => $theme->theme->light->toArray(), 'dark' => $theme->theme->dark->toArray()], true),
            ],
            'stylesheet' => (string) file_get_contents(SwatchesServiceProvider::path('resources/dist/explorer.css')),
        ]);
    }
}
