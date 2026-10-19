<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use Gcego\Swatches\Tokens\TokenSet;

/**
 * The token overrides a Brand produces over one theme, and what could not be applied.
 */
final readonly class CompiledBrand
{
    /**
     * @param  list<string>  $notes  why a requested color was adjusted or not applied
     */
    public function __construct(
        public Brand $brand,
        public string $theme,
        public TokenSet $light,
        public TokenSet $dark,
        public array $notes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->light->isEmpty() && $this->dark->isEmpty();
    }
}
