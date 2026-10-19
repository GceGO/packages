<?php

declare(strict_types=1);

namespace Gcego\Modules\Lifecycle;

/**
 * Stages of module compilation, in order. Discovery, registration, routing
 * and per-request authorization belong to gcego/registry and gcego/pages;
 * these are the stages gcego/modules owns.
 */
enum ModuleStage: string
{
    /** The definition passed its own validation. */
    case Validated = 'validated';

    /** Extensions were applied (and the result validated). */
    case Extended = 'extended';

    /** Resources and relations were normalized (names, operations, sources, requirements). */
    case Normalized = 'normalized';

    /** The module's pages were derived. */
    case PagesDerived = 'pages_derived';
}
