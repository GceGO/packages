<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * A configuration node that tells a component's parent how to place it (for
 * example a grid column span), without changing the component itself.
 *
 * Every component accepts placement configuration, whatever its allowed
 * configuration nodes, because it belongs to the parent's layout rather than
 * to the component. Components never read it; their parents do.
 */
interface PlacementConfiguration extends ConfigurationNode {}
