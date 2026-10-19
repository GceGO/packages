<?php

declare(strict_types=1);

namespace Gcego\Swatches;

use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\Branding\BrandCompiler;
use Gcego\Swatches\Branding\CompiledBrand;
use Gcego\Swatches\Branding\LogoColorExtractor;
use Gcego\Swatches\Branding\LogoPalette;
use Gcego\Swatches\Caching\CompiledCssCache;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Contracts\BrandResolver;
use Gcego\Swatches\Exceptions\InvalidColorException;
use Gcego\Swatches\Exceptions\InvalidLogoException;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Generators\CssGenerator;
use Gcego\Swatches\Palettes\Palette;
use Gcego\Swatches\Palettes\PaletteRegistry;
use Gcego\Swatches\Palettes\ScaleGenerator;
use Gcego\Swatches\Themes\Accent;
use Gcego\Swatches\Themes\Accents;
use Gcego\Swatches\Themes\AppearanceMode;
use Gcego\Swatches\Themes\ResolvedTheme;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeRegistry;
use Gcego\Swatches\Themes\ThemeSource;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\Token;
use Gcego\Swatches\Validation\ThemeValidator;
use Gcego\Swatches\Validation\ValidationReport;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;

/**
 * The color system's entry point (also the Swatches facade).
 *
 * Holds application-wide registrations only. The theme, appearance and brand of a
 * request are always passed in or resolved per call (BrandResolver is scoped), so
 * nothing request-specific survives between requests in long-running workers.
 */
final class SwatchesManager
{
    /**
     * Compiled CSS memo for registered themes; keyed by registry version and theme, never by tenant.
     *
     * @var array<string, string>
     */
    private array $compiled = [];

    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly PaletteRegistry $palettes,
        private readonly SwatchesConfig $config,
        private readonly CssGenerator $css,
        private readonly BrandCompiler $brands,
        private readonly CompiledCssCache $cache,
        private readonly Container $container,
    ) {}

    // Palettes ---------------------------------------------------------------

    public function palette(string $name): Palette
    {
        return $this->palettes->get($name);
    }

    /**
     * @return array<string, Palette>
     */
    public function palettes(): array
    {
        return $this->palettes->all();
    }

    public function registerPalette(Palette $palette, bool $override = false): void
    {
        $this->palettes->register($palette, $override);
    }

    /**
     * An 11-step scale from one brand color (see ScaleGenerator).
     */
    public function generatePalette(string $name, Color|string $color, ?string $label = null): Palette
    {
        return (new ScaleGenerator)->generate($name, $color instanceof Color ? $color : Color::parse($color), $label);
    }

    // Tokens and themes ------------------------------------------------------

    /**
     * @throws InvalidTokenException
     */
    public function token(string $name): Token
    {
        return SemanticTokens::all()[$name] ?? throw InvalidTokenException::unknown($name);
    }

    /**
     * The concrete color of a token in a theme and mode.
     */
    public function color(string $token, ?string $theme = null, bool $dark = false): Color
    {
        return $this->resolve($theme)->color($token, $dark);
    }

    public function theme(?string $name = null): Theme
    {
        return $this->themes->get($name ?? $this->config->theme);
    }

    public function resolve(?string $name = null): ResolvedTheme
    {
        return $this->themes->resolve($name ?? $this->config->theme);
    }

    public function hasTheme(string $name): bool
    {
        return $this->themes->has($name);
    }

    /**
     * @return array<string, Theme>
     */
    public function themes(): array
    {
        return $this->themes->all();
    }

    /**
     * Register a theme; structural errors (broken references, unknown parent, cycles) are rejected.
     *
     * @throws InvalidThemeException
     */
    public function registerTheme(Theme $theme, bool $override = false): void
    {
        $this->themes->register($theme, $override);
        $this->compiled = [];
        $report = $this->validate($theme->name);
        $structural = array_filter($report->errors(), fn ($issue): bool => $issue->code !== 'contrast' || $this->config->enforceContrast);

        if ($structural !== []) {
            throw InvalidThemeException::failsValidation($theme->name, reset($structural)->message);
        }
    }

    public function defaultTheme(): string
    {
        return $this->config->theme;
    }

    /**
     * @param  bool|null  $apca  advisory APCA warnings (null: gcego-swatches.validation.apca)
     * @param  bool|null  $colorVision  advisory color-vision warnings (null: validation.color_vision)
     */
    public function validate(?string $theme = null, ?bool $apca = null, ?bool $colorVision = null): ValidationReport
    {
        $validator = new ThemeValidator($this->themes, apca: $apca ?? $this->config->apca, colorVision: $colorVision ?? $this->config->colorVision);

        return $theme === null ? $validator->validateAll() : $validator->validate($theme);
    }

    /**
     * @throws InvalidColorException
     */
    public function contrast(Color|string $foreground, Color|string $background, Color|string|null $backdrop = null): float
    {
        return Contrast::ratio($foreground, $background, $backdrop);
    }

    // Branding ---------------------------------------------------------------

    /**
     * The current request's brand from the bound (scoped) BrandResolver.
     */
    public function currentBrand(): ?Brand
    {
        return $this->container->make(BrandResolver::class)->brand();
    }

    /**
     * The theme to render: an explicit (panel) theme, else the brand's preset when it
     * names a registered theme, else the configured default.
     */
    public function activeTheme(?string $theme = null, ?Brand $brand = null): string
    {
        if ($theme !== null) {
            return $this->themes->get($theme)->name;
        }

        $brand ??= $this->currentBrand();

        return $brand?->theme !== null && $this->themes->has($brand->theme) ? $brand->theme : $this->config->theme;
    }

    /**
     * The prominent colors of a local logo file (PNG, JPEG, GIF, WebP or SVG) and the brand they suggest.
     *
     * @throws InvalidLogoException
     */
    public function logoColors(string $path): LogoPalette
    {
        return (new LogoColorExtractor)->extract($path);
    }

    /**
     * A Brand suggested by a logo; compile or render it like any other (colors that cannot
     * be made accessible are adjusted or left out by BrandCompiler).
     */
    public function brandFromLogo(string $path, ?string $theme = null): Brand
    {
        return $this->logoColors($path)->suggestBrand($theme);
    }

    public function compileBrand(Brand $brand, ?string $theme = null): CompiledBrand
    {
        return $this->brands->compile($brand, $this->resolve($this->activeTheme($theme, $brand)));
    }

    // Appearance -------------------------------------------------------------

    public function appearance(): AppearanceMode
    {
        return $this->config->appearance;
    }

    public function allowsSwitching(): bool
    {
        return $this->config->allowSwitching;
    }

    public function storageKey(): string
    {
        return $this->config->storageKey;
    }

    /**
     * The main colors users may pick, or none when accents are off.
     *
     * @return array<string, Accent>
     */
    public function accents(): array
    {
        return $this->config->accents ? Accents::all($this->config->accentNames) : [];
    }

    // HTML -------------------------------------------------------------------

    /**
     * Attributes for <html>: `<html {{ $swatches->rootAttributes() }}>`.
     */
    public function rootAttributes(?string $theme = null, ?Brand $brand = null): HtmlString
    {
        $default = $this->config->appearance;

        return new HtmlString(sprintf(
            'data-gcego-theme="%s" data-gcego-appearance="%s"%s',
            e($this->activeTheme($theme, $brand)),
            e($default->value),
            $default === AppearanceMode::Dark ? ' class="dark"' : '',
        ));
    }

    /**
     * The appearance controller source (resources/js/appearance.js), for inlining in <head>.
     */
    public function appearanceScript(): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/resources/js/appearance.js');
    }

    /**
     * The complete inline <script> for <x-gcego-swatches::appearance>. Built here, not in
     * Blade, so template formatters cannot alter the script body.
     */
    public function appearanceTag(?string $nonce = null): HtmlString
    {
        return new HtmlString(sprintf(
            '<script data-gcego-appearance-controller data-default="%s" data-allow-switching="%s" data-storage-key="%s" data-accents="%s" data-accent-key="%s"%s>%s</script>',
            e($this->config->appearance->value),
            $this->config->allowSwitching ? 'true' : 'false',
            e($this->config->storageKey),
            e(implode(' ', array_keys($this->accents()))),
            e($this->config->accentStorageKey),
            self::nonce($nonce),
            $this->appearanceScript(),
        ));
    }

    /**
     * Runtime CSS of a theme that is not in the static stylesheet: '' for "gcego", and for
     * built-in presets when css.static_presets says the stylesheet imports themes.css.
     */
    public function themeCss(?string $theme = null): string
    {
        $resolved = $this->resolve($this->activeTheme($theme));

        if ($resolved->name() === Theme::Base || ($resolved->theme->source === ThemeSource::BuiltIn && $this->config->staticPresets)) {
            return '';
        }

        return $this->compiledEntry('themes', $resolved->name(), fn (): string => $this->css->theme($resolved, $this->themes->resolve(Theme::Base), minify: true));
    }

    /**
     * The <style> with a theme's custom-property overrides, or nothing when none are needed.
     */
    public function themeTag(?string $theme = null, ?string $nonce = null): HtmlString
    {
        return self::style('data-gcego-theme-styles', $this->themeCss($theme), $nonce);
    }

    public function accentCss(?string $theme = null): string
    {
        $accents = $this->accents();

        if ($accents === []) {
            return '';
        }

        $resolved = $this->resolve($this->activeTheme($theme));

        return $this->compiledEntry('accents', $resolved->name(), fn (): string => $this->css->accents($accents, $resolved));
    }

    /**
     * The <style> of the accents users may pick, plus a "default" swatch of the theme's own
     * primary color; nothing when accents are off.
     */
    public function accentTag(?string $theme = null, ?string $nonce = null): HtmlString
    {
        return self::style('data-gcego-accent-styles', $this->accentCss($theme), $nonce);
    }

    /**
     * The tenant brand's overrides (from $brand, else the current BrandResolver), or nothing.
     * Place it after themeTag() and before accentTag(). $standalone targets plain :root, for
     * pages that do not render rootAttributes() (such as gcego/sentinel's sign-in pages).
     */
    public function brandCss(?Brand $brand = null, ?string $theme = null, bool $standalone = false): string
    {
        $brand ??= $this->currentBrand();

        if ($brand === null || ($brand->primary === null && $brand->accent === null)) {
            return '';
        }

        $compiled = $this->compileBrand($brand, $theme);

        return $this->css->brand($compiled->light, $compiled->dark, $standalone ? ':root' : ':root[data-gcego-theme]');
    }

    public function brandTag(?Brand $brand = null, ?string $theme = null, ?string $nonce = null): HtmlString
    {
        return self::style('data-gcego-brand-styles', $this->brandCss($brand, $theme), $nonce);
    }

    // Stylesheets ------------------------------------------------------------

    /**
     * The package's static stylesheets (resources/css), regenerated by gcego:swatches:generate --package.
     *
     * @return array{'tokens.css': string, 'tailwind.css': string, 'themes.css': string}
     */
    public function packageStylesheets(): array
    {
        $base = $this->themes->resolve(Theme::Base);
        $builtIn = [];

        foreach ($this->themes->all() as $name => $theme) {
            if ($theme->source === ThemeSource::BuiltIn && $name !== Theme::Base) {
                $builtIn[] = $this->themes->resolve($name);
            }
        }

        return [
            'tokens.css' => $this->css->tokens(),
            'tailwind.css' => $this->css->tailwind(),
            'themes.css' => "/*\n * Built-in theme presets (Gcego\\Swatches\\Themes\\BuiltInThemes), generated by\n * \"php artisan gcego:swatches:generate --package\". Each block holds only what differs from \"gcego\".\n * Optional: import it and set gcego-swatches.css.static_presets = true to ship every preset\n * statically; otherwise <x-gcego-swatches::theme> prints only the active preset.\n */\n\n".$this->css->themes($builtIn, $base),
        ];
    }

    /**
     * The application's build-time stylesheet: configured palettes as Tailwind theme
     * variables and every non-built-in theme. Import it after the GCEGO styles.
     */
    public function applicationStylesheet(): string
    {
        $base = $this->themes->resolve(Theme::Base);
        $themes = [];

        foreach ($this->themes->all() as $name => $theme) {
            if ($theme->source !== ThemeSource::BuiltIn) {
                $themes[] = $this->themes->resolve($name);
            }
        }

        $parts = array_filter([
            $this->css->palettes(array_values($this->palettes->custom())),
            $this->css->themes($themes, $base),
        ]);

        return "/*\n * Generated by \"php artisan gcego:swatches:generate\" from config/gcego-swatches.php. Do not edit.\n */\n"
            .($parts === [] ? '' : "\n".implode("\n", $parts));
    }

    // Caching ----------------------------------------------------------------

    /**
     * Identity of everything compiled CSS depends on.
     */
    public function fingerprint(): string
    {
        $themes = array_map(static fn (Theme $theme): array => $theme->toArray(), $this->themes->all());

        return hash('xxh128', (string) json_encode([CompiledCssCache::Version, $themes, array_keys($this->accents()), SemanticTokens::Definitions, $this->config->staticPresets]));
    }

    /**
     * Compile every registered theme's runtime CSS and accents into the cache file.
     *
     * @return array{themes: int, accents: int}
     */
    public function warmCache(): array
    {
        $this->compiled = [];
        $themes = [];
        $accents = [];

        foreach ($this->themes->names() as $name) {
            $resolved = $this->themes->resolve($name);

            if ($name !== Theme::Base) {
                $themes[$name] = $this->css->theme($resolved, $this->themes->resolve(Theme::Base), minify: true);
            }

            if ($this->accents() !== []) {
                $accents[$name] = $this->css->accents($this->accents(), $resolved);
            }
        }

        $this->cache->write($this->fingerprint(), $themes, $accents);

        return ['themes' => count($themes), 'accents' => count($accents)];
    }

    public function clearCache(): bool
    {
        $this->compiled = [];

        return $this->cache->clear();
    }

    public function cache(): CompiledCssCache
    {
        return $this->cache;
    }

    public function config(): SwatchesConfig
    {
        return $this->config;
    }

    public function generator(): CssGenerator
    {
        return $this->css;
    }

    public function registry(): ThemeRegistry
    {
        return $this->themes;
    }

    /**
     * @param  'themes'|'accents'  $kind
     * @param  callable(): string  $compile
     */
    private function compiledEntry(string $kind, string $theme, callable $compile): string
    {
        $key = $kind.'|'.$this->themes->version().'|'.$theme;

        if (isset($this->compiled[$key])) {
            return $this->compiled[$key];
        }

        $cached = $this->cache->read();

        if ($cached !== null && $cached['fingerprint'] === $this->fingerprint() && isset($cached[$kind][$theme])) {
            return $this->compiled[$key] = $cached[$kind][$theme];
        }

        return $this->compiled[$key] = $compile();
    }

    private static function style(string $marker, string $css, ?string $nonce): HtmlString
    {
        return new HtmlString($css === '' ? '' : sprintf('<style %s%s>%s</style>', $marker, self::nonce($nonce), $css));
    }

    private static function nonce(?string $nonce): string
    {
        return $nonce === null || $nonce === '' ? '' : ' nonce="'.e($nonce).'"';
    }
}
