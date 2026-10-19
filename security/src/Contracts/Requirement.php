<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Data\Contracts\SourceConfiguration;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;

/**
 * A declarative authorization requirement, attached to any component
 * (blueprint, data source, future panel, page or action) as a configuration node:
 *
 *     new Blueprint('subjects', new Permission('subjects.view'), new Source(Subject::class));
 *
 * Every requirement on a component and on its ancestors must allow. A
 * component with no requirement anywhere on its path is denied.
 */
interface Requirement extends SourceConfiguration
{
    /**
     * @param  mixed  $resource  The record or class being accessed, if any.
     */
    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision;

    /**
     * A short safe description for diagnostics: "permission subjects.view".
     */
    public function describe(): string;
}
