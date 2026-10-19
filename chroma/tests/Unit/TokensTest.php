<?php

declare(strict_types=1);

use Gcego\Chroma\Exceptions\InvalidTokenException;
use Gcego\Chroma\Tokens\DefaultTokens;
use Gcego\Chroma\Tokens\DesignToken;
use Gcego\Chroma\Tokens\TokenCollection;
use Gcego\Chroma\Tokens\TokenType;
use Gcego\Swatches\Tokens\SemanticTokens;

it('keeps tokens.css identical to the PHP definitions', function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/tokens.css');
    [$light, $dark] = explode(':root.dark', $css);

    foreach (DefaultTokens::Definitions as $name => [$type, $lightValue, $darkValue]) {
        expect($light)->toContain("--gcego-{$name}: {$lightValue};")
            ->and($dark)->toContain("--gcego-{$name}: {$darkValue};");
    }

    expect(substr_count($css, '--gcego-'))->toBe(2 * count(DefaultTokens::Definitions));
});

it('exposes every non-color token to Tailwind through theme.css and leaves colors to gcego/swatches', function (): void {
    $theme = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/theme.css');
    $entry = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/gcego.css');

    expect($theme)->toContain('@theme inline')
        ->and($theme)->toContain('--radius-gcego: var(--gcego-radius);')
        ->and($theme)->toContain('--shadow-gcego-card: var(--gcego-shadow-card);')
        ->and($theme)->not->toContain('--color-')
        ->and($entry)->toContain('@import "../../../swatches/resources/css/swatches.css";')
        ->and($entry)->not->toContain('@custom-variant');
});

it('defines no color tokens of its own', function (): void {
    foreach (DefaultTokens::names() as $name) {
        expect(SemanticTokens::has($name))->toBeFalse($name);
    }

    expect(DefaultTokens::colorNames())->toBe(SemanticTokens::names());
});

it('validates token values by type', function (TokenType $type, string $value, bool $valid): void {
    expect($type->accepts($value))->toBe($valid);
})->with([
    [TokenType::Length, '1rem;} body{display:none', false],
    [TokenType::Shadow, '0 0 1px url(https://evil.test/x)', false],
    [TokenType::Length, '1rem', true],
    [TokenType::Length, '0', true],
    [TokenType::Length, '12', false],
    [TokenType::Length, 'calc(1rem)', false],
    [TokenType::FontFamily, '"Inter", ui-sans-serif, sans-serif', true],
    [TokenType::FontFamily, 'Inter</style><script>', false],
    [TokenType::Shadow, '0 1px 2px 0 rgb(0 0 0 / 0.1)', true],
    [TokenType::Shadow, 'expression(alert(1))', false],
    [TokenType::Duration, '150ms', true],
    [TokenType::Duration, 'fast', false],
]);

it('rejects unknown tokens and invalid values without echoing them', function (): void {
    expect(fn () => new DesignToken('radus', '1rem'))->toThrow(InvalidTokenException::class, '"radus" is not a GCEGO design token')
        ->and(fn () => new DesignToken('radius', '1rem;}<script>'))->toThrow(InvalidTokenException::class, 'The value of token "radius" is not a valid length')
        ->and(fn () => new DesignToken('primary', '#0e7490'))->toThrow(InvalidTokenException::class, 'colors belong to gcego/swatches');

    try {
        new DesignToken('radius', '1rem;}<script>');
    } catch (InvalidTokenException $exception) {
        expect($exception->getMessage())->not->toContain('<script>');
    }
});

it('builds token collections', function (): void {
    $tokens = TokenCollection::light()->with(new TokenCollection(['radius' => '1rem']));

    expect($tokens->get('radius'))->toBe('1rem')
        ->and($tokens->get('radius-lg'))->toBe('0.75rem')
        ->and($tokens->get('missing'))->toBeNull()
        ->and(TokenCollection::dark()->get('shadow-card'))->toBe(DefaultTokens::Definitions['shadow-card'][2])
        ->and((new TokenCollection(['radius' => ' 1rem ']))->declarations())->toBe('--gcego-radius: 1rem;')
        ->and(count(TokenCollection::light()))->toBe(count(DefaultTokens::Definitions))
        ->and((new DesignToken('radius', '1rem'))->property())->toBe('--gcego-radius');
});
