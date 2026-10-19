<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

/**
 * A part whose own BlueprintPart children are blueprint parts too.
 *
 * Implement it on layout-like parts (sections, tabs) that group other parts.
 * Without it, a part's children are its private content: a table's row
 * actions never count as blueprint actions.
 */
interface PartContainer extends BlueprintPart {}
