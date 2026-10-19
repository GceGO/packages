<?php

declare(strict_types=1);

use Gcego\Chroma\ChromaManager;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Accents;

// The accent presets live in gcego/swatches (Themes\Accents, tested there); chroma delegates to them.

it('offers the gcego/swatches accents through the styling manager', function (): void {
    expect(array_keys(app(ChromaManager::class)->accents()))->toBe(array_keys(Accents::Presets))
        ->and((string) app(ChromaManager::class)->accentTag())->toBe((string) app(SwatchesManager::class)->accentTag());
});

it('follows the gcego/swatches accent configuration', function (): void {
    $this->rebuildApplication(['gcego-swatches.accents.only' => ['red', 'nope', 'blue']]);

    expect(array_keys(app(ChromaManager::class)->accents()))->toBe(['red', 'blue']);
});
