<?php

declare(strict_types=1);

namespace Gcego\Swatches\Accessibility;

use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Themes\ResolvedTheme;
use Gcego\Swatches\Tokens\ContrastRequirement;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * Checks every SemanticTokens::ContrastPairs pair of a theme in light and dark mode.
 *
 * Translucent tokens are composited over the mode's "background" token. A failing
 * pair carries a suggested foreground (Contrast::suggest) that would pass.
 */
final class ContrastAuditor
{
    /**
     * @param  list<array{string, string, ContrastRequirement, string}>|null  $pairs
     * @return list<ContrastResult>
     *
     * @throws InvalidTokenException when a pair's token does not resolve
     */
    public function audit(ResolvedTheme $theme, ?array $pairs = null): array
    {
        return [...$this->auditSet($theme->light, false, $pairs), ...$this->auditSet($theme->dark, true, $pairs)];
    }

    /**
     * @param  list<array{string, string, ContrastRequirement, string}>|null  $pairs
     * @return list<ContrastResult>
     */
    public function auditSet(TokenSet $tokens, bool $dark, ?array $pairs = null): array
    {
        $backdrop = $tokens->resolve('background');
        $results = [];

        foreach ($pairs ?? SemanticTokens::ContrastPairs as [$foreground, $background, $requirement, $purpose]) {
            $front = $tokens->resolve($foreground);
            $behind = $tokens->resolve($background);
            $ratio = Contrast::ratio($front, $behind, $backdrop);
            $suggestion = $ratio < $requirement->minimum() ? Contrast::suggest($front, $behind, $requirement->minimum(), $backdrop) : null;

            $results[] = new ContrastResult($foreground, $background, $requirement, $purpose, $dark, $front, $behind, $ratio, $suggestion, Apca::contrast($front, $behind, $backdrop));
        }

        return $results;
    }
}
