<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Illuminate\Contracts\Config\Repository;

/**
 * Proves renderers are built once, with constructor injection.
 */
final class CountingRenderer implements RendererInterface
{
    public static int $instances = 0;

    public function __construct(public readonly Repository $config)
    {
        self::$instances++;
    }

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        return RenderResult::text('counted:'.$context->path());
    }
}
