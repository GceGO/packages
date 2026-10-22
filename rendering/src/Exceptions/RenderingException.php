<?php

declare(strict_types=1);

namespace Gcego\Rendering\Exceptions;

use Throwable;

/**
 * Marker implemented by every exception thrown by gcego/rendering.
 *
 * Render authorization failures are Laravel's AuthorizationException (HTTP 403).
 */
interface RenderingException extends Throwable {}
