<?php

declare(strict_types=1);

namespace Gcego\Security\Exceptions;

/**
 * The tenant is unknown, or the user has no verified membership in it.
 * Rendered as 404 by default so other tenants' existence is not revealed.
 */
final class TenantAccessException extends AccessDeniedException {}
