<?php

declare(strict_types=1);

namespace Gcego\Pages\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Presets\PagePresets;
use Gcego\Rendering\Blade\BladeRenderer;
use Gcego\Rendering\Context\RenderContext;

/**
 * Renders Content as escaped paragraphs.
 */
final class ContentRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'gcego-pages::components.content';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        return [
            'paragraphs' => $component instanceof Content ? $component->paragraphs() : [],
            'preset' => implode(' ', PagePresets::Content),
        ];
    }
}
