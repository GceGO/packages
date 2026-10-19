<?php

declare(strict_types=1);

use Gcego\Luminaries\Exceptions\DuplicateRegistrationException;
use Gcego\Luminaries\Exceptions\IconNotFoundException;
use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\Exceptions\InvalidIconNameException;
use Gcego\Luminaries\Exceptions\UnsafeSvgException;
use Gcego\Luminaries\Facades\Luminaries;
use Illuminate\Support\Facades\Log;

it('resolves canonical names in every provider', function (string $name, string $canonical): void {
    expect(Luminaries::resolve($name)->canonical())->toBe($canonical);
})->with([
    'heroicon outline' => ['heroicon:academic-cap', 'heroicon:academic-cap'],
    'heroicon solid' => ['heroicon-solid:academic-cap', 'heroicon-solid:academic-cap'],
    'heroicon mini' => ['heroicon-mini:academic-cap', 'heroicon-mini:academic-cap'],
    'heroicon micro' => ['heroicon-micro:academic-cap', 'heroicon-micro:academic-cap'],
    'lucide' => ['lucide:graduation-cap', 'lucide:graduation-cap'],
    'custom collection' => ['custom:gcego-logo', 'custom:gcego-logo'],
    'bare name through the default provider' => ['pencil-square', 'heroicon:pencil-square'],
]);

it('renders heroicons, lucide and custom icons from their own artwork', function (string $name, string $fragment): void {
    expect((string) Luminaries::render($name))->toContain($fragment);
})->with([
    'heroicon' => ['heroicon:academic-cap', 'd="M4.26 10.147a60.438'],
    'lucide' => ['lucide:graduation-cap', 'd="M21.42 10.922'],
    'custom' => ['custom:gcego-logo', 'd="M12 3 3 8l9 5 9-5-9-5Z"'],
]);

it('resolves semantic aliases through chains and records the chain', function (): void {
    $icon = Luminaries::resolve('status.danger');

    expect($icon->canonical())->toBe('heroicon:x-circle')
        ->and($icon->aliasChain)->toBe(['status.danger', 'status.error']);
});

it('lets the application override a preset alias through configuration', function (): void {
    configureLuminaries(['aliases' => ['actions.edit' => 'lucide:pencil']]);

    expect(Luminaries::resolve('actions.edit')->canonical())->toBe('lucide:pencil');
});

it('lets packages register aliases at boot and refuses silent remapping', function (): void {
    Luminaries::alias('reports.weekly', 'lucide:calendar-range');

    expect(Luminaries::resolve('reports.weekly')->canonical())->toBe('lucide:calendar-range')
        ->and(fn () => Luminaries::alias('reports.weekly', 'heroicon:calendar'))->toThrow(DuplicateRegistrationException::class);
});

it('explains which mapping is broken when an alias target is missing', function (): void {
    Luminaries::alias('actions.shelve', 'lucide:archive-box');

    expect(fn () => Luminaries::resolve('actions.shelve'))->toThrow(function (IconNotFoundException $exception): void {
        expect($exception->getMessage())
            ->toContain("GCEGO Luminaries\n\nIcon not found: actions.shelve")
            ->toContain('Provider: semantic')
            ->toContain('Configured mapping: lucide:archive-box')
            ->toContain('The icon is not available in the installed collection.')
            ->toContain('lucide:archive');
    });
});

it('reports undefined aliases with suggestions taken from registered aliases', function (): void {
    expect(fn () => Luminaries::resolve('actions.edti'))->toThrow(IconNotFoundException::class, 'Registered icons with similar names: actions.edit');
});

it('reports unknown providers', function (): void {
    expect(fn () => Luminaries::resolve('fontawesome:user'))->toThrow(IconNotFoundException::class, 'No provider named "fontawesome" is registered or enabled.');
});

it('does not resolve icons a variant lacks', function (): void {
    expect(Luminaries::exists('heroicon:academic-cap'))->toBeTrue()
        ->and(Luminaries::exists('heroicon-micro:does-not-exist'))->toBeFalse()
        ->and(Luminaries::exists('lucide:pencil-square-xyz'))->toBeFalse();
});

it('answers exists() with false for malformed names instead of throwing', function (): void {
    expect(Luminaries::exists('../../etc/passwd'))->toBeFalse()
        ->and(fn () => Luminaries::resolve('../../etc/passwd'))->toThrow(InvalidIconNameException::class);
});

it('renders decorative icons hidden from assistive technology', function (): void {
    $svg = (string) Luminaries::render('actions.edit');

    expect($svg)->toContain('aria-hidden="true"')
        ->toContain('focusable="false"')
        ->not->toContain('role="img"')
        ->not->toContain('aria-label');
});

it('renders labelled icons as images with an accessible name and no aria-hidden', function (): void {
    $svg = (string) Luminaries::render('actions.edit', label: 'Edit subject');

    expect($svg)->toContain('role="img"')
        ->toContain('aria-label="Edit subject"')
        ->not->toContain('aria-hidden');
});

it('keeps an icon decorative when decorative is requested even with a label', function (): void {
    expect((string) Luminaries::render('actions.edit', label: 'Edit', decorative: true))->toContain('aria-hidden="true"')->not->toContain('aria-label');
});

it('escapes labels', function (): void {
    $svg = (string) Luminaries::render('actions.edit', label: '"><script>alert(1)</script>');

    expect($svg)->toContain('&lt;script&gt;')->not->toContain('<script>');
});

it('maps size and tone tokens to design-system classes', function (): void {
    $svg = (string) Luminaries::render('actions.save', size: 'xl', tone: 'success', class: 'ml-2');

    expect($svg)->toContain('class="shrink-0 size-8 text-gcego-success ml-2"');
});

it('uses the default size and inherits currentColor when no tone is given', function (): void {
    $svg = (string) Luminaries::render('actions.save');

    expect($svg)->toContain('class="shrink-0 size-5"')->toContain('stroke="currentColor"');
});

it('omits the size class for size none', function (): void {
    expect((string) Luminaries::render('actions.save', size: 'none'))->toContain('class="shrink-0"');
});

it('refuses unknown size and tone tokens', function (string $size, ?string $tone, string $message): void {
    expect(fn () => Luminaries::render('actions.save', size: $size, tone: $tone))->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'size' => ['gigantic', null, 'unknown size "gigantic"'],
    'tone' => ['md', 'neon', 'unknown tone "neon"'],
]);

it('applies stroke width only to stroke-drawn providers', function (): void {
    expect((string) Luminaries::render('lucide:pencil', strokeWidth: 1.25))->toContain('stroke-width="1.25"')->not->toContain('stroke-width="2"')
        ->and((string) Luminaries::render('heroicon-solid:pencil', strokeWidth: 1.25))->not->toContain('stroke-width');
});

it('renders safe extra attributes and refuses dangerous ones', function (): void {
    $svg = (string) Luminaries::render('actions.save', attributes: ['id' => 'save', 'data-test' => 'a"b', 'wire:key' => 'k1', 'x-show' => 'open']);

    expect($svg)->toContain('id="save"')->toContain('data-test="a&quot;b"')->toContain('wire:key="k1"')->toContain('x-show="open"');
});

it('refuses event handlers and other unsafe attributes', function (string $attribute): void {
    expect(fn () => Luminaries::render('actions.save', attributes: [$attribute => 'x']))->toThrow(UnsafeSvgException::class, 'is not allowed on icons');
})->with(['onload', 'onclick', 'style', 'href', 'xlink:href', 'aria-hidden', 'x onload', 'data-x"onload']);

it('renders inline path icons registered at boot, before the default provider', function (): void {
    Luminaries::registerPath('pencil-square', 'M1 1h22v22H1z');

    expect(Luminaries::resolve('pencil-square')->canonical())->toBe('inline:pencil-square')
        ->and((string) Luminaries::render('pencil-square'))->toContain('d="M1 1h22v22H1z"');
});

it('refuses inline path data that is not path commands', function (): void {
    Luminaries::registerPath('school', 'M0 0"/><script>alert(1)</script>');
})->throws(InvalidConfigurationException::class, 'must use SVG path data only');

it('refuses custom icons that violate the trusted SVG policy', function (string $icon): void {
    expect(Luminaries::exists("unsafe:{$icon}"))->toBeFalse()
        ->and(fn () => Luminaries::resolve("unsafe:{$icon}"))->toThrow(UnsafeSvgException::class, 'rejected by the trusted SVG policy');
})->with(['script', 'handler', 'external', 'foreign', 'entity', 'javascript-url']);

it('never renders an unsafe custom icon, whatever the missing behavior', function (): void {
    configureLuminaries(['missing' => ['behavior' => 'placeholder']]);

    $svg = (string) Luminaries::render('unsafe:script');

    expect($svg)->toContain('data-gcego-icon-missing="unsafe:script"')->not->toContain('alert');
});

it('refuses symlinks that leave the collection directory', function (): void {
    $outside = tempnam(sys_get_temp_dir(), 'lum');
    file_put_contents($outside, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>');
    $link = fixtures('icons/trusted/escape.svg');
    symlink($outside, $link);

    try {
        expect(Luminaries::exists('custom:escape'))->toBeFalse();
    } finally {
        unlink($link);
        unlink($outside);
    }
});

it('refuses collections outside the trusted roots', function (): void {
    configureLuminaries(['collections' => ['system' => ['path' => sys_get_temp_dir()]]]);

    expect(fn () => Luminaries::resolve('system:anything'))->toThrow(IconNotFoundException::class, 'outside the trusted roots');
});

describe('missing icons', function (): void {
    it('throws in exception mode', function (): void {
        configureLuminaries(['missing' => ['behavior' => 'exception', 'fallback' => 'heroicon:question-mark-circle', 'log' => true]]);

        Luminaries::render('actions.nope');
    })->throws(IconNotFoundException::class, 'Icon not found: actions.nope');

    it('logs once and renders the fallback, keeping the accessible label', function (): void {
        configureLuminaries(['missing' => ['behavior' => 'fallback', 'fallback' => 'heroicon:question-mark-circle', 'log' => true]]);
        Log::spy();

        $first = (string) Luminaries::render('actions.nope', label: 'Archive');
        Luminaries::render('actions.nope');

        expect($first)->toContain('data-gcego-icon="heroicon:question-mark-circle"')->toContain('aria-label="Archive"');
        Log::shouldHaveReceived('warning')->once();
    });

    it('renders a neutral placeholder naming the icon', function (): void {
        configureLuminaries(['missing' => ['behavior' => 'placeholder', 'fallback' => 'heroicon:question-mark-circle', 'log' => true]]);

        expect((string) Luminaries::render('actions.nope'))->toContain('data-gcego-icon-missing="actions.nope"')->toContain('stroke-dasharray');
    });

    it('renders only the label as screen-reader text in empty mode', function (): void {
        configureLuminaries(['missing' => ['behavior' => 'empty', 'fallback' => 'heroicon:question-mark-circle', 'log' => true]]);

        expect((string) Luminaries::render('actions.nope', label: 'Archive'))->toBe('<span class="sr-only">Archive</span>')
            ->and((string) Luminaries::render('actions.nope'))->toBe('');
    });

    it('treats malformed names as missing outside exception mode', function (): void {
        configureLuminaries(['missing' => ['behavior' => 'placeholder', 'fallback' => 'heroicon:question-mark-circle', 'log' => true]]);

        expect((string) Luminaries::render('<script>'))->toContain('data-gcego-icon-missing="invalid"')->not->toContain('<script>');
    });
});

it('renders nothing for unknown or null data-driven icons through svg()', function (): void {
    expect((string) Luminaries::svg(null))->toBe('')
        ->and((string) Luminaries::svg('not-an-icon-at-all'))->toBe('')
        ->and((string) Luminaries::svg('"><script>'))->toBe('')
        ->and((string) Luminaries::svg('check', 'size-4'))->toContain('class="shrink-0 size-4"');
});

it('searches icons and aliases, ranking exact names before partial matches', function (): void {
    $names = array_column(Luminaries::search('trash', limit: 500), 'name');

    expect(array_slice($names, 0, 5))->toBe(['heroicon-micro:trash', 'heroicon-mini:trash', 'heroicon-solid:trash', 'heroicon:trash', 'lucide:trash'])
        ->and($names)->toContain('lucide:trash-2')
        ->not->toContain('lucide:pencil');
});

it('filters search results by provider', function (): void {
    $results = Luminaries::search('cap', 'lucide', 500);

    expect(array_unique(array_column($results, 'provider')))->toBe(['lucide']);
});

it('removes duplicate classes while keeping their order', function (): void {
    expect((string) Luminaries::render('actions.save', size: 'sm', class: 'shrink-0 size-4 ml-1'))->toContain('class="shrink-0 size-4 ml-1"');
});
