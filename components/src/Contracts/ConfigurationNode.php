<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * A non-renderable configuration object passed to a component constructor.
 *
 * Configuration nodes (for example a future styling modifier) are kept apart
 * from renderable children: they never appear in children(), traversal or
 * child-count rules, and are exposed through configurations() instead.
 */
interface ConfigurationNode extends Normalizable {}
