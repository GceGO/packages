<?php

declare(strict_types=1);

namespace Gcego\Testing\Exceptions;

use Throwable;

/**
 * Marker for every exception the toolkit throws itself. Assertion failures
 * are PHPUnit's own AssertionFailedError and do not implement this.
 */
interface TestingException extends Throwable {}
