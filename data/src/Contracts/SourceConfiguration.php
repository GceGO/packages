<?php

declare(strict_types=1);

namespace Gcego\Data\Contracts;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * A configuration node accepted by Source (Filter, Sort, Paginate, Query, ...).
 *
 * Packages may implement it for their own source options; the compiler
 * keeps unknown implementations in SourceDefinition::$extensions.
 */
interface SourceConfiguration extends ConfigurationNode {}
