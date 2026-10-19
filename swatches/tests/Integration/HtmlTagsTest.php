<?php

declare(strict_types=1);

use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\Contracts\BrandResolver;
use Gcego\Swatches\SwatchesManager;
use Illuminate\Support\Facades\Blade;

it('renders root attributes for the html element', function (): void {
    expect((string) app(SwatchesManager::class)->rootAttributes())->toBe('data-gcego-theme="gcego" data-gcego-appearance="system"')
        ->and((string) app(SwatchesManager::class)->rootAttributes('ocean'))->toBe('data-gcego-theme="ocean" data-gcego-appearance="system"');

    $this->rebuildApplication(['gcego-swatches.appearance.default' => 'dark']);

    expect((string) app(SwatchesManager::class)->rootAttributes())->toBe('data-gcego-theme="gcego" data-gcego-appearance="dark" class="dark"');
});

it('inlines the appearance controller with its configuration', function (): void {
    $html = Blade::render('<x-gcego-swatches::appearance />');

    expect($html)->toContain('<script data-gcego-appearance-controller data-default="system" data-allow-switching="true" data-storage-key="gcego.appearance" data-accents="teal blue indigo violet rose red orange amber green" data-accent-key="gcego.accent">')
        ->and($html)->toContain('window.GcegoAppearance')
        ->and($html)->not->toContain('nonce=');
});

// A separate test: Blade's anonymous-component hint does not survive rebuilding the application.
it('supports a CSP nonce and configured appearance defaults', function (): void {
    $this->rebuildApplication(['gcego-swatches.appearance.default' => 'dark', 'gcego-swatches.appearance.allow_switching' => false]);

    expect(Blade::render('<x-gcego-swatches::appearance nonce="abc123" />'))
        ->toContain('data-default="dark" data-allow-switching="false"')
        ->toContain('nonce="abc123"');
});

it('emits runtime theme CSS for the active preset or configured theme, never for gcego', function (): void {
    $this->rebuildApplication(['gcego-swatches.themes' => ['campus' => ['light' => ['primary' => '#1d4ed8'], 'dark' => ['primary' => '#60a5fa']]]]);

    expect(trim(Blade::render('<x-gcego-swatches::theme />')))->toBe('')
        ->and(Blade::render('<x-gcego-swatches::theme name="ocean" />'))->toContain(':root[data-gcego-theme="ocean"]:not(.dark){--gcego-background:#f4f7f9;')
        ->and(Blade::render('<x-gcego-swatches::theme name="ocean" />'))->not->toContain('midnight')
        ->and(Blade::render('<x-gcego-swatches::theme name="campus" nonce="n1" />'))->toContain('<style data-gcego-theme-styles nonce="n1">:root[data-gcego-theme="campus"]:not(.dark){--gcego-primary:#1d4ed8;}:root.dark[data-gcego-theme="campus"]{--gcego-primary:#60a5fa;}</style>');
});

it('leaves presets to the stylesheet when it imports themes.css', function (): void {
    $this->rebuildApplication(['gcego-swatches.css.static_presets' => true]);

    expect((string) app(SwatchesManager::class)->themeTag('ocean'))->toBe('');
});

it('emits the main colors users may pick, or none when accents are off', function (): void {
    $tag = (string) app(SwatchesManager::class)->accentTag(nonce: 'abc123');

    expect($tag)->toStartWith('<style data-gcego-accent-styles nonce="abc123">[data-gcego-accent-swatch="default"]{')
        ->toContain('--gcego-primary:#0b7570;')
        ->toContain(':root[data-gcego-accent="green"]:not(.dark)');

    $this->rebuildApplication(['gcego-swatches.accents.only' => ['red', 'blue']]);

    expect(array_keys(app(SwatchesManager::class)->accents()))->toBe(['red', 'blue'])
        ->and((string) app(SwatchesManager::class)->appearanceTag())->toContain('data-accents="red blue"');

    $this->rebuildApplication(['gcego-swatches.accents.enabled' => false]);

    expect((string) app(SwatchesManager::class)->accentTag())->toBe('')
        ->and((string) app(SwatchesManager::class)->appearanceTag())->toContain('data-accents=""');
});

it('renders the tenant brand from the bound resolver and picks its theme preset', function (): void {
    app()->scoped(BrandResolver::class, fn (): BrandResolver => new class implements BrandResolver
    {
        public function brand(): ?Brand
        {
            return Brand::fromArray(['primary' => '#2563eb', 'theme' => 'slate']);
        }
    });

    $swatches = app(SwatchesManager::class);

    expect((string) $swatches->brandTag(nonce: 'n2'))->toStartWith('<style data-gcego-brand-styles nonce="n2">:root[data-gcego-theme]:not(.dark){')
        ->toContain('--gcego-primary:#2563eb;')
        ->and((string) $swatches->rootAttributes())->toContain('data-gcego-theme="slate"')
        ->and((string) $swatches->rootAttributes('ocean'))->toContain('data-gcego-theme="ocean"');
});

it('ignores a brand preset that is not registered', function (): void {
    expect(app(SwatchesManager::class)->activeTheme(brand: Brand::fromArray(['theme' => 'unknown'])))->toBe('gcego');
});

it('renders everything for <head> in order with one component', function (): void {
    $html = Blade::render('<x-gcego-swatches::head :brand="$brand" nonce="n3" />', ['brand' => Brand::fromArray(['primary' => '#2563eb'])]);

    expect(strpos($html, 'data-gcego-appearance-controller'))->toBeLessThan((int) strpos($html, 'data-gcego-brand-styles'))
        ->and(strpos($html, 'data-gcego-brand-styles'))->toBeLessThan((int) strpos($html, 'data-gcego-accent-styles'));
});

it('renders an accessible appearance switcher', function (): void {
    $html = Blade::render('<x-gcego-swatches::appearance-switcher />');

    expect($html)->toContain('role="group"')
        ->toContain('data-gcego-appearance-set="light"')
        ->toContain('data-gcego-appearance-set="dark"')
        ->toContain('data-gcego-appearance-set="system"')
        ->toContain('aria-pressed="false"')
        ->toContain('class="sr-only"');
});
