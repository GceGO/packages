<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;

final readonly class TrustedHtmlRenderer implements RendererInterface
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        return RenderResult::trustedHtml('<strong>trusted markup</strong>');
    }
}
