<?php

declare(strict_types=1);

namespace Gcego\Pages\Contracts;

use Gcego\Pages\Lifecycle\PageLifecycleEvent;

/**
 * Trusted code called at every page lifecycle stage (gcego-pages.lifecycle.hooks
 * or PageManager::hook()), for auditing, metrics or extra checks:
 *
 *     final class AuditPageViews implements PageLifecycleHook
 *     {
 *         public function handle(PageLifecycleEvent $event): void
 *         {
 *             if ($event->stage === PageStage::Authorized) { … }
 *         }
 *     }
 *
 * Hooks run after the stage they describe, so they never see an unauthorized
 * page or record. They may stop a request by throwing (for example an
 * AccessDeniedException); they cannot grant access.
 */
interface PageLifecycleHook
{
    public function handle(PageLifecycleEvent $event): void;
}
