<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tokens;

/**
 * Non-color token value types and the strict value grammar each accepts
 * (color values are parsed by gcego/swatches).
 *
 * Values are emitted inside <style> blocks, so every grammar excludes
 * `;`, `{`, `}`, `<`, `>`, `\` and url().
 */
enum TokenType: string
{
    case Length = 'length';
    case FontFamily = 'font-family';
    case Shadow = 'shadow';
    case Duration = 'duration';

    public function accepts(string $value): bool
    {
        if ($value === '' || strlen($value) > 300 || preg_match('/[;{}<>\\\\]|url\s*\(|expression\s*\(|@import|\/\*/i', $value) === 1) {
            return false;
        }

        return match ($this) {
            self::Length => preg_match('/^(?:0|-?\d*\.?\d+(?:px|rem|em|%|vh|vw|ch))$/', $value) === 1,
            self::FontFamily => preg_match('/^[a-z0-9 ,"\'-]+$/i', $value) === 1,
            self::Shadow => $value === 'none' || preg_match('/^[a-z0-9 .,%()\/#-]+$/i', $value) === 1,
            self::Duration => preg_match('/^\d+(?:\.\d+)?(?:ms|s)$/', $value) === 1,
        };
    }
}
