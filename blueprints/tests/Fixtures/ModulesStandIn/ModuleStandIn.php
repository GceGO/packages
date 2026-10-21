<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\ModulesStandIn;

/**
 * Stands in for Gcego\Modules\Module, whose package cannot be installed here.
 */
final readonly class ModuleStandIn
{
    public function __construct(public string $name) {}
}
