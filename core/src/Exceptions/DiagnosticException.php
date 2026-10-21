<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use LogicException;

/** Invalid check registration or recursive diagnostic execution. */
class DiagnosticException extends LogicException implements GcegoException {}
