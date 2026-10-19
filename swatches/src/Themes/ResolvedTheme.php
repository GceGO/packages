<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * A theme with its inheritance chain applied: complete light and dark token sets.
 */
final readonly class ResolvedTheme
{
    /**
     * @param  list<string>  $chain  base first, this theme last
     */
    public function __construct(
        public Theme $theme,
        public array $chain,
        public TokenSet $light,
        public TokenSet $dark,
    ) {}

    public function name(): string
    {
        return $this->theme->name;
    }

    public function tokens(bool $dark = false): TokenSet
    {
        return $dark ? $this->dark : $this->light;
    }

    public function color(string $token, bool $dark = false): Color
    {
        return $this->tokens($dark)->resolve($token);
    }
}
