<?php

declare(strict_types=1);

namespace Gcego\Data\Integration;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;

/**
 * gcego/runtime renderer for Source parts: a data source has no markup.
 * Registered only when gcego/runtime is installed. It never queries.
 */
final class SourceRenderer implements RendererInterface
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        return RenderResult::empty();
    }
}
