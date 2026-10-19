<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tokens;

use Gcego\Swatches\Tokens\SemanticTokens;

/**
 * The GCEGO non-color design tokens (radius, shadows, typography, motion) with their
 * light and dark defaults. Colors belong to gcego/swatches (SemanticTokens).
 *
 * This is the single source of truth: resources/css/tokens.css must declare
 * exactly these values (a test enforces it). Each token is a CSS custom
 * property `--gcego-<name>`, exposed to Tailwind by resources/css/theme.css
 * (`rounded-gcego-lg`, `shadow-gcego-card`, `font-gcego`, …).
 */
final class DefaultTokens
{
    /**
     * name => [type, light value, dark value]
     *
     * @var array<string, array{0: TokenType, 1: string, 2: string}>
     */
    public const array Definitions = [
        'radius' => [TokenType::Length, '0.5rem', '0.5rem'],
        'radius-sm' => [TokenType::Length, '0.375rem', '0.375rem'],
        'radius-lg' => [TokenType::Length, '0.75rem', '0.75rem'],
        'shadow-card' => [TokenType::Shadow, '0 1px 2px 0 rgb(15 23 41 / 0.05), 0 1px 3px 0 rgb(15 23 41 / 0.04)', '0 1px 2px 0 rgb(0 0 0 / 0.45)'],
        'shadow-overlay' => [TokenType::Shadow, '0 12px 32px -8px rgb(15 23 41 / 0.18), 0 4px 8px -4px rgb(15 23 41 / 0.08)', '0 16px 40px -8px rgb(0 0 0 / 0.65), 0 4px 12px -4px rgb(0 0 0 / 0.4)'],
        'font-sans' => [TokenType::FontFamily, 'ui-sans-serif, system-ui, sans-serif', 'ui-sans-serif, system-ui, sans-serif'],
        'duration' => [TokenType::Duration, '150ms', '150ms'],
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::Definitions);
    }

    /**
     * Color token names (owned by gcego/swatches), for utility classification.
     *
     * @return list<string>
     */
    public static function colorNames(): array
    {
        return SemanticTokens::names();
    }

    public static function type(string $name): ?TokenType
    {
        return isset(self::Definitions[$name]) ? self::Definitions[$name][0] : null;
    }
}
