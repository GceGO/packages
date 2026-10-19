<?php

declare(strict_types=1);

namespace Gcego\Modules\Contracts;

use Gcego\Modules\Module;

/**
 * A trusted extension of modules, applied when a module is compiled (in
 * the order of gcego-modules.extensions, then runtime registrations):
 *
 *     final class AuditTrail implements ModuleExtension
 *     {
 *         public function extends(string $module): bool { return $module === 'academics'; }
 *
 *         public function extend(Module $module): Module
 *         {
 *             return $module->withAppendedChildren(new DashboardPage(...));   // add a page, a resource...
 *         }
 *     }
 *
 * It returns a new Module (definitions are immutable); the module keeps its
 * name, and the result is validated like any module. Extensions come from
 * configuration or code only, never from requests. Derived pages are
 * indexed by gcego/registry: rebuild its cache after changing extensions.
 */
interface ModuleExtension
{
    /**
     * Whether this extension applies to the named module.
     */
    public function extends(string $module): bool;

    public function extend(Module $module): Module;
}
