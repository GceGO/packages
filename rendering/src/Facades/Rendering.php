<?php

declare(strict_types=1);

namespace Gcego\Rendering\Facades;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\RenderEngine;
use Gcego\Runtime\Execution\Operation;
use Illuminate\Support\Facades\Facade;

/**
 * @method static RenderResult render(ComponentInterface|string $target, RenderMode $mode = RenderMode::Static, ?string $identity = null, ?string $panel = null, ?string $theme = null, int $headingLevel = 1, array<string, mixed> $metadata = [], array<string, array<string, mixed>> $state = [])
 * @method static RenderResult renderAuthorized(string $identifier, RenderMode $mode = RenderMode::Static, ?string $panel = null, ?string $theme = null, int $headingLevel = 1, array<string, mixed> $metadata = [], array<string, array<string, mixed>> $state = [])
 * @method static BlueprintInterface authorizedBlueprint(string $identifier)
 * @method static string identityOf(string $identifier)
 * @method static bool canRender(ComponentInterface $component)
 * @method static Operation operation(string $target)
 *
 * @see RenderEngine
 */
final class Rendering extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RenderEngine::class;
    }
}
