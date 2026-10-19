<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * Explicit opt-in for runtime-only property values such as callbacks.
 *
 * Runtime values are stored as-is and never inspected, serialized or invoked
 * by this package. Normalization replaces them with a RuntimePlaceholder that
 * only carries the implementing class name.
 */
interface RuntimeValue {}
