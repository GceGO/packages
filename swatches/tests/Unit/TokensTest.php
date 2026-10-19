<?php

declare(strict_types=1);

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\TokenGroup;
use Gcego\Swatches\Tokens\TokenSet;
use Gcego\Swatches\Tokens\TokenValue;

it('defines every token the specification and gcego/chroma rely on', function (string $token): void {
    expect(SemanticTokens::has($token))->toBeTrue();
})->with([
    'background', 'foreground', 'surface', 'surface-foreground', 'surface-muted', 'surface-muted-foreground',
    'card', 'card-foreground', 'popover', 'popover-foreground', 'primary', 'primary-foreground',
    'secondary', 'secondary-foreground', 'accent', 'accent-foreground', 'muted', 'muted-foreground',
    'border', 'input', 'ring', 'success', 'success-foreground', 'warning', 'warning-foreground',
    'danger', 'danger-foreground', 'info', 'info-foreground', 'chart-1', 'chart-6',
    // The original gcego/chroma names, still used as text-gcego-text etc.
    'text', 'text-muted', 'border-strong', 'primary-hover', 'primary-soft',
]);

it('keeps the original gcego/chroma color values', function (): void {
    $light = SemanticTokens::light();
    $dark = SemanticTokens::dark();

    expect($light->resolve('text')->toHex())->toBe('#0f1729')
        ->and($light->resolve('text-muted')->toHex())->toBe('#4a5468')
        ->and($light->resolve('primary')->toHex())->toBe('#0b7570')
        ->and($light->resolve('ring')->toHex())->toBe('#0b7570')
        ->and($dark->resolve('surface')->toHex())->toBe('#11151c')
        ->and($dark->resolve('ring')->toHex())->toBe('#3ec2b5');
});

it('resolves every token in both modes', function (): void {
    foreach ([SemanticTokens::light(), SemanticTokens::dark()] as $set) {
        expect(count($set))->toBe(count(SemanticTokens::Definitions));

        foreach (SemanticTokens::names() as $name) {
            expect($set->resolve($name))->toBeInstanceOf(Color::class);
        }
    }
});

it('marks only the original aliases as legacy', function (): void {
    $legacy = array_keys(array_filter(SemanticTokens::all(), static fn ($token): bool => $token->group === TokenGroup::Legacy));

    expect($legacy)->toBe(['text', 'text-muted']);
});

it('follows references and reports broken ones and cycles', function (): void {
    $set = SemanticTokens::light()->with(new TokenSet(['link' => '{info}']));

    expect($set->resolve('link')->toHex())->toBe('#1f5fc4')
        ->and($set->dependents('primary'))->toContain('ring', 'sidebar-active-foreground')
        ->and($set->dependents('primary-hover'))->toContain('primary-active', 'link-hover')
        ->and($set->dependents('primary'))->not->toContain('link');

    $broken = new TokenSet(['link' => '{does-not-exist}']);
    $cycle = new TokenSet(['link' => '{link-hover}', 'link-hover' => '{link}']);

    expect(fn () => $broken->resolve('link'))->toThrow(InvalidTokenException::class, 'refers to "does-not-exist"')
        ->and(fn () => $cycle->resolve('link'))->toThrow(InvalidTokenException::class, 'cycle')
        ->and(fn () => $set->resolve('nope'))->toThrow(InvalidTokenException::class);
});

it('accepts only catalog tokens and color or reference values', function (): void {
    expect(fn () => new TokenSet(['primry' => '#000']))->toThrow(InvalidTokenException::class, '"primry" is not a GCEGO color token')
        ->and(fn () => new TokenSet(['primary' => 'red;}body{display:none']))->toThrow(InvalidTokenException::class)
        ->and(fn () => new TokenSet(['primary' => ['#000']]))->toThrow(InvalidTokenException::class)
        ->and(fn () => TokenValue::parse('primary', '{Not Valid}'))->toThrow(InvalidTokenException::class);
});

it('keeps catalog order and serializes values, never the input', function (): void {
    $set = new TokenSet(['ring' => '{primary}', 'primary' => 'RGB(11 117 112)', 'background' => 'White']);

    expect($set->names())->toBe(['background', 'ring', 'primary'])
        ->and($set->declarations())->toBe(['--gcego-background: white;', '--gcego-ring: var(--gcego-primary);', '--gcego-primary: rgb(11 117 112);'])
        ->and($set->toArray())->toBe(['background' => 'white', 'ring' => '{primary}', 'primary' => 'rgb(11 117 112)']);
});

it('diffs and narrows token sets', function (): void {
    $base = SemanticTokens::light();
    $theme = $base->with(new TokenSet(['primary' => '#123456']));

    expect($theme->diff($base)->toArray())->toBe(['primary' => '#123456'])
        ->and($theme->only(['primary', 'ring'])->names())->toBe(['ring', 'primary'])
        ->and($base->diff($base)->isEmpty())->toBeTrue();
});
