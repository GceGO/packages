<?php

declare(strict_types=1);

namespace Gcego\Swatches\Generators;

use Gcego\Swatches\Palettes\Palette;
use Gcego\Swatches\Themes\Accent;
use Gcego\Swatches\Themes\ResolvedTheme;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\Token;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * Builds every CSS artifact of the color system, deterministically.
 *
 * Selectors are built only from validated identifiers (theme and accent names)
 * and values only from TokenValue::toCss() (re-serialized colors or
 * var(--gcego-<token>)), so no input string ever reaches the output verbatim.
 *
 * Mode and theme selectors match gcego/chroma's appearance controller:
 *   light       :root               dark        :root.dark
 *   theme       :root[data-gcego-theme="x"]:not(.dark)  and  :root.dark[data-gcego-theme="x"]
 */
final class CssGenerator
{
    /**
     * resources/css/tokens.css: the catalog defaults (light on :root, dark differences on :root.dark).
     */
    public function tokens(): string
    {
        $light = SemanticTokens::light();
        $dark = SemanticTokens::dark()->diff($light);

        return $this->header('Generated from Gcego\Swatches\Tokens\SemanticTokens by "php artisan gcego:swatches:generate --package".')
            ."\n"
            .$this->rule(':root', ['color-scheme: light;', ...$light->declarations()])
            ."\n"
            .$this->rule(':root.dark', ['color-scheme: dark;', ...$dark->declarations()]);
    }

    /**
     * resources/css/tailwind.css: the Tailwind CSS v4 bridge.
     *
     * "@theme inline" makes each utility reference the custom property directly, so
     * values switch with mode, theme and brand at runtime without rebuilding CSS.
     */
    public function tailwind(): string
    {
        $canonical = [];
        $prefixed = [];

        foreach (SemanticTokens::all() as $token) {
            if ($token->hasCanonicalUtility()) {
                $canonical[] = sprintf('--color-%s: var(%s);', $token->name, Token::property($token->name));
            }

            if ($token->hasPrefixedUtility()) {
                $prefixed[] = sprintf('--color-gcego-%s: var(%s);', $token->name, Token::property($token->name));
            }
        }

        return $this->header(
            'Generated from Gcego\Swatches\Tokens\SemanticTokens by "php artisan gcego:swatches:generate --package".',
            '',
            'Exposes every color token to Tailwind CSS v4: bg-surface, text-foreground, border-border,',
            'bg-primary text-primary-foreground, ring-ring, fill-chart-1 … The original gcego/chroma',
            'colors keep their gcego-* spellings too (bg-gcego-surface, text-gcego-text-muted).',
            'The dark: variant follows the appearance controller (the "dark" class on <html>).',
        )
            ."\n@custom-variant dark (&:where(.dark, .dark *));\n\n"
            .$this->rule('@theme inline', [...$canonical, '', ...$prefixed]);
    }

    /**
     * resources/css/themes.css (built-in presets), or a theme's runtime <style>.
     *
     * Each theme emits only what differs from the base theme in each mode.
     */
    public function theme(ResolvedTheme $theme, ResolvedTheme $base, bool $minify = false): string
    {
        if ($theme->name() === Theme::Base) {
            return '';
        }

        $name = $theme->name();
        $light = $theme->light->diff($base->light);
        $dark = $theme->dark->diff($base->dark);
        $css = [];

        if (! $light->isEmpty()) {
            $css[] = $this->rule(sprintf(':root[data-gcego-theme="%s"]:not(.dark)', $name), $light->declarations(), $minify);
        }

        if (! $dark->isEmpty()) {
            $css[] = $this->rule(sprintf(':root.dark[data-gcego-theme="%s"]', $name), $dark->declarations(), $minify);
        }

        return implode($minify ? '' : "\n", $css);
    }

    /**
     * @param  list<ResolvedTheme>  $themes
     */
    public function themes(array $themes, ResolvedTheme $base, bool $minify = false): string
    {
        $css = array_filter(array_map(fn (ResolvedTheme $theme): string => $this->theme($theme, $base, $minify), $themes));

        return implode($minify ? '' : "\n", $css);
    }

    /**
     * The user-pickable accents (<html data-gcego-accent="red">) and their swatches
     * (data-gcego-accent-swatch="red"), plus a "default" swatch of the theme's own color.
     *
     * Swatches are descendant scopes, so tokens that reference an accent token
     * (ring → primary, link → primary …) are re-declared there to follow it.
     *
     * @param  array<string, Accent>  $accents
     */
    public function accents(array $accents, ResolvedTheme $theme): string
    {
        if ($accents === []) {
            return '';
        }

        $own = static fn (TokenSet $tokens): TokenSet => $tokens->only(Accent::Tokens);
        $css = $this->rule('[data-gcego-accent-swatch="default"]', $this->scoped($own($theme->light), $theme->light), true)
            .$this->rule(':root.dark [data-gcego-accent-swatch="default"]', $this->scoped($own($theme->dark), $theme->dark), true);

        foreach ($accents as $accent) {
            $swatch = sprintf('[data-gcego-accent-swatch="%s"]', $accent->name);

            $css .= $this->rule(sprintf(':root[data-gcego-accent="%s"]:not(.dark)', $accent->name), $accent->light->declarations(), true)
                .$this->rule($swatch, $this->scoped($accent->light, $theme->light), true)
                .$this->rule(sprintf(':root.dark[data-gcego-accent="%s"]', $accent->name), $accent->dark->declarations(), true)
                .$this->rule(':root.dark '.$swatch, $this->scoped($accent->dark, $theme->dark), true);
        }

        return $css;
    }

    /**
     * Tenant brand overrides. The selectors outrank theme selectors only by order
     * (same specificity), so emit brand styles after theme styles and before accents.
     *
     * @param  string  $root  ':root[data-gcego-theme]' (pages with rootAttributes()) or ':root' (standalone pages without themes)
     */
    public function brand(TokenSet $light, TokenSet $dark, string $root = ':root[data-gcego-theme]'): string
    {
        $root = $root === ':root' ? ':root' : ':root[data-gcego-theme]';
        $css = '';

        if (! $light->isEmpty()) {
            $css .= $this->rule($root.':not(.dark)', $light->declarations(), true);
        }

        if (! $dark->isEmpty()) {
            $css .= $this->rule(str_replace(':root', ':root.dark', $root), $dark->declarations(), true);
        }

        return $css;
    }

    /**
     * Application palettes as Tailwind theme variables (bg-brand-600, text-brand-50 …).
     *
     * @param  list<Palette>  $palettes
     */
    public function palettes(array $palettes): string
    {
        $declarations = [];

        foreach ($palettes as $palette) {
            foreach ($palette->colors as $step => $color) {
                $declarations[] = sprintf('--color-%s-%d: %s;', $palette->name, $step, $color->toCss());
            }
        }

        return $declarations === [] ? '' : $this->rule('@theme', $declarations);
    }

    /**
     * Declarations for a descendant scope: $values plus every token in $context that
     * references one of them (re-declared so it resolves inside the scope).
     *
     * @return list<string>
     */
    public function scoped(TokenSet $values, TokenSet $context): array
    {
        $dependents = [];

        foreach ($values->names() as $name) {
            foreach ($context->dependents($name) as $dependent) {
                if (! $values->has($dependent)) {
                    $dependents[] = $dependent;
                }
            }
        }

        return [...$values->declarations(), ...$context->only(array_values(array_unique($dependents)))->declarations()];
    }

    /**
     * @param  list<string>  $declarations
     */
    private function rule(string $selector, array $declarations, bool $minify = false): string
    {
        if ($minify) {
            return $selector.'{'.implode('', array_map(static fn (string $line): string => str_replace(': ', ':', $line), array_filter($declarations, static fn (string $line): bool => $line !== ''))).'}';
        }

        $body = implode("\n", array_map(static fn (string $line): string => $line === '' ? '' : '    '.$line, $declarations));

        return $selector." {\n".$body."\n}\n";
    }

    private function header(string ...$lines): string
    {
        return "/*\n".implode("\n", array_map(static fn (string $line): string => rtrim(' * '.$line), $lines))."\n */\n";
    }
}
