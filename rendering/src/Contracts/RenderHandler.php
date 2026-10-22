<?php

declare(strict_types=1);

namespace Gcego\Rendering\Contracts;

use Gcego\Runtime\Contracts\OperationHandler;

/**
 * Handler contract of the "render" operation category, which gcego/rendering
 * declares in gcego/runtime's HandlerRegistry.
 */
interface RenderHandler extends OperationHandler {}
