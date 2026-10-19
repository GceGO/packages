<?php

declare(strict_types=1);

namespace Gcego\Swatches\Facades;

use Gcego\Swatches\SwatchesManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Gcego\Swatches\Palettes\Palette palette(string $name)
 * @method static array<string, \Gcego\Swatches\Palettes\Palette> palettes()
 * @method static void registerPalette(\Gcego\Swatches\Palettes\Palette $palette, bool $override = false)
 * @method static \Gcego\Swatches\Palettes\Palette generatePalette(string $name, \Gcego\Swatches\Colors\Color|string $color, ?string $label = null)
 * @method static \Gcego\Swatches\Tokens\Token token(string $name)
 * @method static \Gcego\Swatches\Colors\Color color(string $token, ?string $theme = null, bool $dark = false)
 * @method static \Gcego\Swatches\Themes\Theme theme(?string $name = null)
 * @method static \Gcego\Swatches\Themes\ResolvedTheme resolve(?string $name = null)
 * @method static array<string, \Gcego\Swatches\Themes\Theme> themes()
 * @method static void registerTheme(\Gcego\Swatches\Themes\Theme $theme, bool $override = false)
 * @method static \Gcego\Swatches\Validation\ValidationReport validate(?string $theme = null)
 * @method static float contrast(\Gcego\Swatches\Colors\Color|string $foreground, \Gcego\Swatches\Colors\Color|string $background, \Gcego\Swatches\Colors\Color|string|null $backdrop = null)
 * @method static \Gcego\Swatches\Branding\LogoPalette logoColors(string $path)
 * @method static \Gcego\Swatches\Branding\Brand brandFromLogo(string $path, ?string $theme = null)
 * @method static \Gcego\Swatches\Branding\CompiledBrand compileBrand(\Gcego\Swatches\Branding\Brand $brand, ?string $theme = null)
 * @method static string activeTheme(?string $theme = null, ?\Gcego\Swatches\Branding\Brand $brand = null)
 * @method static \Illuminate\Support\HtmlString rootAttributes(?string $theme = null, ?\Gcego\Swatches\Branding\Brand $brand = null)
 * @method static \Illuminate\Support\HtmlString appearanceTag(?string $nonce = null)
 * @method static \Illuminate\Support\HtmlString themeTag(?string $theme = null, ?string $nonce = null)
 * @method static \Illuminate\Support\HtmlString brandTag(?\Gcego\Swatches\Branding\Brand $brand = null, ?string $theme = null, ?string $nonce = null)
 * @method static \Illuminate\Support\HtmlString accentTag(?string $theme = null, ?string $nonce = null)
 *
 * @see SwatchesManager
 */
final class Swatches extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SwatchesManager::class;
    }
}
