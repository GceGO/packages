<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * A definition-time builder that produces a component.
 *
 * Builders may be passed wherever a child component is accepted (component
 * constructors, other builders, definition files); they are built into the
 * real, immutable component when their parent is constructed.
 */
interface Buildable
{
    public function build(): ComponentInterface;
}
