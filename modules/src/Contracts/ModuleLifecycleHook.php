<?php

declare(strict_types=1);

namespace Gcego\Modules\Contracts;

use Gcego\Modules\Lifecycle\ModuleLifecycleEvent;

/**
 * Observes module compilation (listed in gcego-modules.lifecycle.hooks).
 * Hooks cannot change definitions (use a ModuleExtension) and must not query.
 */
interface ModuleLifecycleHook
{
    public function handle(ModuleLifecycleEvent $event): void;
}
