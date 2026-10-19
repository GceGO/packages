<?php

declare(strict_types=1);

namespace Gcego\Modules\Lifecycle;

use Gcego\Modules\Definitions\ModuleDefinition;

/**
 * A compilation stage a module passed. $definition is set from Normalized on;
 * $pages lists the derived page names at PagesDerived.
 */
final readonly class ModuleLifecycleEvent
{
    /**
     * @param  list<string>  $pages
     */
    public function __construct(
        public ModuleStage $stage,
        public string $module,
        public ?ModuleDefinition $definition = null,
        public array $pages = [],
    ) {}
}
