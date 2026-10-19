<?php

declare(strict_types=1);

namespace Gcego\Tables\Contracts;

use Gcego\Components\Contracts\ComponentInterface;

/**
 * Marker for the components a Table accepts besides actions: columns and filters.
 */
interface TableComponent extends ComponentInterface {}
