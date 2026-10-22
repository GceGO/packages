<?php

declare(strict_types=1);

namespace Gcego\Rendering\Styling;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Contracts\StylingResolver;

/**
 * The default until gcego/chroma is installed: no classes, so built-in
 * markup is plain semantic HTML.
 */
final readonly class NullStylingResolver implements StylingResolver
{
    public function classes(ComponentInterface $component, string $slot, RenderContext $context): string
    {
        return '';
    }
}
