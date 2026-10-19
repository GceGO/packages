<?php

declare(strict_types=1);

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Themes\Theme;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Theme as ColorTheme;
use Illuminate\Support\Facades\Blade;

it('inlines the appearance controller with its configuration', function (): void {
    $html = Blade::render('<x-gcego-chroma::appearance />');

    expect($html)->toContain('<script data-gcego-appearance-controller data-default="system" data-allow-switching="true" data-storage-key="gcego.appearance" data-accents="teal blue indigo violet rose red orange amber green" data-accent-key="gcego.accent">')
        ->and($html)->toContain('window.GcegoAppearance')
        ->and($html)->not->toContain('nonce=');
});

it('supports a CSP nonce and configured defaults', function (): void {
    $this->rebuildApplication(['gcego-swatches.appearance.default' => 'dark', 'gcego-swatches.appearance.allow_switching' => false]);

    expect(Blade::render('<x-gcego-chroma::appearance nonce="abc123" />'))
        ->toContain('data-default="dark" data-allow-switching="false"')
        ->toContain('nonce="abc123"');
});

it('emits theme custom properties only for themes that override tokens', function (): void {
    app(ChromaManager::class)->registerTheme(new Theme('roomy', ['radius' => '1rem']));
    app(SwatchesManager::class)->registerTheme(new ColorTheme('roomy', ['primary' => '#0e7490'], ['primary' => '#22d3ee']));

    expect(trim(Blade::render('<x-gcego-chroma::theme />')))->toBe('')
        ->and(Blade::render('<x-gcego-chroma::theme name="ocean" />'))->toContain(':root[data-gcego-theme="ocean"]:not(.dark){')
        ->and(Blade::render('<x-gcego-chroma::theme name="roomy" />'))->toContain('<style data-gcego-theme-styles>:root[data-gcego-theme="roomy"]:not(.dark){--gcego-primary:#0e7490;}:root.dark[data-gcego-theme="roomy"]{--gcego-primary:#22d3ee;}:root[data-gcego-theme="roomy"]:not(.dark){--gcego-radius: 1rem;}</style>');
});

it('renders root attributes for the html element', function (): void {
    expect((string) app(ChromaManager::class)->rootAttributes())->toBe('data-gcego-theme="gcego" data-gcego-appearance="system"');

    $this->rebuildApplication(['gcego-swatches.appearance.default' => 'dark']);

    expect((string) app(ChromaManager::class)->rootAttributes())->toBe('data-gcego-theme="gcego" data-gcego-appearance="dark" class="dark"');
});

it('emits the main colors users may pick, with a swatch of the theme\'s own color', function (): void {
    $tag = (string) app(ChromaManager::class)->accentTag(nonce: 'abc123');

    expect($tag)->toStartWith('<style data-gcego-accent-styles nonce="abc123">[data-gcego-accent-swatch="default"]{')
        ->toContain('--gcego-primary:#0b7570;')
        ->toContain(':root[data-gcego-accent="red"]:not(.dark)')
        ->toContain(':root[data-gcego-accent="green"]:not(.dark)');
});

it('offers only the configured main colors, or none when accents are off', function (): void {
    $this->rebuildApplication(['gcego-swatches.accents.only' => ['red', 'blue']]);

    expect(array_keys(app(ChromaManager::class)->accents()))->toBe(['red', 'blue'])
        ->and((string) app(ChromaManager::class)->appearanceTag())->toContain('data-accents="red blue"');

    $this->rebuildApplication(['gcego-swatches.accents.enabled' => false]);

    expect((string) app(ChromaManager::class)->accentTag())->toBe('')
        ->and((string) app(ChromaManager::class)->appearanceTag())->toContain('data-accents=""');
});

it('still honours appearance and accents set in an old gcego-chroma config', function (): void {
    $this->rebuildApplication(['gcego-chroma.appearance' => ['default' => 'dark', 'allow_switching' => false], 'gcego-chroma.accents' => ['only' => ['red']]]);

    expect((string) app(ChromaManager::class)->rootAttributes())->toContain('data-gcego-appearance="dark" class="dark"')
        ->and(app(ChromaManager::class)->allowsSwitching())->toBeFalse()
        ->and(array_keys(app(ChromaManager::class)->accents()))->toBe(['red']);
});

it('prefers gcego-swatches over the old gcego-chroma keys', function (): void {
    $this->rebuildApplication(['gcego-chroma.appearance' => ['default' => 'dark'], 'gcego-swatches.appearance' => ['default' => 'light']]);

    expect(app(SwatchesManager::class)->appearance()->value)->toBe('light');
});
