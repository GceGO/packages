<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * An immutable object that may be stored as a property value.
 *
 * Implementations promise immutability (ideally a readonly class whose state
 * is itself immutable). The engine never clones, mutates or constructs them.
 */
interface ValueObject extends Normalizable {}
