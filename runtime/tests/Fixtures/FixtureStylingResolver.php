<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * Stand-in for gcego/chroma: static, fully written class names per slot.
 */
final readonly class FixtureStylingResolver implements StylingResolver
{
    public function classes(ComponentInterface $component, string $slot, RenderContext $context): string
    {
        return match ($slot) {
            'root' => 'rounded-2xl border bg-white shadow-sm',
            'title' => 'text-lg font-semibold',
            'evil' => '" onmouseover="alert(1)',
            default => '',
        };
    }
}
