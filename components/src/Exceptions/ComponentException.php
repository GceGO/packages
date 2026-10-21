<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use Throwable;

/**
 * Marker implemented by every exception thrown by gcego/morfaw.
 *
 * Catch this interface to handle any component-related failure regardless of
 * the concrete SPL exception family it belongs to.
 */
interface ComponentException extends Throwable {}
