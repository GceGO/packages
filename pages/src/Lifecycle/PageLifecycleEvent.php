<?php

declare(strict_types=1);

namespace Gcego\Pages\Lifecycle;

use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Page;

/**
 * What a PageLifecycleHook is told: the stage that just completed, the page
 * and, from ContextCreated on, its context (with the record from
 * RecordResolved on, and the header from Prepared on).
 */
final readonly class PageLifecycleEvent
{
    public function __construct(
        public PageStage $stage,
        public string $identity,
        public Page $page,
        public ?PageContext $context = null,
    ) {}
}
