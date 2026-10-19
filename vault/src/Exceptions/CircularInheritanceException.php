<?php

declare(strict_types=1);

namespace Gcego\Vault\Exceptions;

/**
 * Role inheritance that would include a role in its own ancestry.
 */
final class CircularInheritanceException extends VaultValidationException {}
