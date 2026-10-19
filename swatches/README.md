# GCEGO Swatches

The color system of GCEGO Admin Studio: color values and conversion (hex, rgb, hsl, OKLCH, OKLab), palettes and brand-scale generation, semantic color tokens, light, dark, system and named themes with inheritance, user accents, tenant branding, WCAG 2.2 AA contrast validation, and the Tailwind CSS v4 color bridge.

```html
<div class="bg-surface text-foreground border border-border">
    <button class="bg-primary text-primary-foreground">Continue</button>
</div>
```

The colors follow the active theme, mode, tenant brand and user accent, with no rebuild. WCAG 2.2 AA is enforced for every theme, APCA and color-vision simulation are reported as advisory checks, and a tenant's colors can be suggested from its logo.

```php
use Gcego\Swatches\Facades\Swatches;

Swatches::palette('blue')->get(600)->toHex();      // "#155dfc"
Swatches::theme('ocean')->label();                 // "GCEGO Ocean"
Swatches::color('primary', 'ocean', dark: true);   // Color
Swatches::contrast('#111827', '#FFFFFF');          // 17.73…
Swatches::validate();                              // ValidationReport
```

```bash
php artisan gcego:swatches:list | themes | validate | contrast | palette | logo | generate | cache | clear
```

Documentation: [`docs/swatches`](../../docs/swatches) (architecture, installation, Tailwind v4, palettes, semantic tokens, themes, accessibility, tenant branding, integration, testing).

## Requirements

PHP 8.3+, Laravel 13, Tailwind CSS v4. Depends only on `gcego/core` and Illuminate.

## License

MIT
