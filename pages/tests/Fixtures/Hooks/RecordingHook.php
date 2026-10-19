<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Hooks;

use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Contracts\PageLifecycleHook;
use Gcego\Pages\Lifecycle\PageLifecycleEvent;

/**
 * Records every lifecycle event as "stage:identity[:record]".
 */
final class RecordingHook implements PageLifecycleHook
{
    /**
     * @var list<string>
     */
    public static array $events = [];

    public function handle(PageLifecycleEvent $event): void
    {
        self::$events[] = $event->stage->value.':'.$event->identity.($event->context instanceof RecordPageContext ? ':'.$event->context->key : '');
    }
}
