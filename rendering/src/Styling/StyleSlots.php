<?php

declare(strict_types=1);

namespace Gcego\Rendering\Styling;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Contracts\StylingResolver;
use Illuminate\Support\HtmlString;

/**
 * Template helper: `{{ $styles('title') }}` or `<h1{{ $styles->attribute('title') }}>`.
 */
final readonly class StyleSlots
{
    public function __construct(
        private StylingResolver $resolver,
        private ComponentInterface $component,
        private RenderContext $context,
    ) {}

    public function __invoke(string $slot): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->resolver->classes($this->component, $slot, $this->context)) ?? '');
    }

    /**
     * ` class="…"` (escaped), or nothing when the slot has no classes.
     */
    public function attribute(string $slot): HtmlString
    {
        $classes = $this($slot);

        return new HtmlString($classes === '' ? '' : ' class="'.e($classes).'"');
    }
}
