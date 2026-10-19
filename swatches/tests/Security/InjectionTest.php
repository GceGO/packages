<?php

declare(strict_types=1);

use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\SwatchesException;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * Pest's toThrow() only accepts classes; every Swatches exception implements the SwatchesException interface.
 */
function rejects(callable $callback): bool
{
    try {
        $callback();
    } catch (SwatchesException) {
        return true;
    }

    return false;
}

dataset('css injection', [
    'close declaration' => ['red; } body { display: none'],
    'close style' => ['#fff</style><script>alert(1)</script>'],
    'url' => ['url(https://evil.test/x.png)'],
    'expression' => ['expression(alert(1))'],
    'import' => ['@import "https://evil.test/x.css"'],
    'escape' => ['\\72 ed'],
    'comment' => ['#fff/**/'],
    'var hijack' => ['var(--x, red); background: url(x)'],
    'nested function' => ['rgb(0 0 0 / calc(1))'],
    'newline' => ["#fff\n}"],
    'null byte' => ["#fff\0"],
]);

it('rejects CSS injection in colors', function (string $value): void {
    expect(rejects(fn () => Color::parse($value)))->toBeTrue();
})->with('css injection');

it('rejects CSS injection in theme tokens and tenant brands', function (string $value): void {
    expect(rejects(fn () => new TokenSet(['primary' => $value])))->toBeTrue()
        ->and(rejects(fn () => Brand::fromArray(['primary' => $value])))->toBeTrue();
})->with('css injection');

it('rejects malicious theme identifiers', function (string $name): void {
    expect(rejects(fn () => new Theme($name)))->toBeTrue()
        ->and(rejects(fn () => new Brand(theme: $name)))->toBeTrue();
})->with([
    'attribute break' => ['ocean"]{}*{color:red}[x="'],
    'path' => ['../ocean'],
    'uppercase' => ['Ocean'],
    'spaces' => ['ocean blue'],
    'markup' => ['<script>'],
    'too long' => [str_repeat('a', 51)],
    'empty' => [''],
]);

it('never echoes raw input in exception messages', function (): void {
    try {
        Color::parse('#fff</style><script>alert(1)</script>');
        $this->fail('expected an exception');
    } catch (SwatchesException $exception) {
        expect($exception->getMessage())->not->toContain('<')->not->toContain('>');
    }
});

it('serializes colors from parsed numbers so emitted CSS is always clean', function (): void {
    $swatches = app(SwatchesManager::class);
    $css = $swatches->brandCss(Brand::fromArray(['primary' => '  RGB( 37 , 99 , 235 )  ', 'accent' => 'HSL(38 92% 50%)']));

    expect($css)->toMatch('/^[:a-z\[\]().\-{}#0-9;-]+$/')
        ->and(substr_count($css, '{'))->toBe(substr_count($css, '}'))
        ->and($css)->not->toContain('RGB')
        ->not->toContain('<')
        ->not->toContain('url(');
});

it('escapes theme names and nonces in HTML attributes', function (): void {
    $swatches = app(SwatchesManager::class);

    expect((string) $swatches->appearanceTag('"><script>'))->toContain('nonce="&quot;&gt;&lt;script&gt;"')
        ->and(rejects(fn () => $swatches->rootAttributes('x" onload="alert(1)')))->toBeTrue();
});
