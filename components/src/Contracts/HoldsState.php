<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * A component that owns a value in the schema state, such as a form field.
 *
 * Its state path is its container's path plus its statePath (or name). The
 * live engine fills its default, runs its afterStateUpdated hook and
 * dehydrates its value while it is visible.
 */
interface HoldsState extends ComponentInterface {}
