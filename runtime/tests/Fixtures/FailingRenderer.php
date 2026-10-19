<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use LogicException;

final readonly class FailingRenderer implements RendererInterface
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        throw new LogicException('database password=secret leaked?');
    }
}
