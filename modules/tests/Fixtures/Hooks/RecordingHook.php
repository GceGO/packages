<?php

declare(strict_types=1);

namespace Gcego\Modules\Tests\Fixtures\Hooks;

use Gcego\Modules\Contracts\ModuleLifecycleHook;
use Gcego\Modules\Lifecycle\ModuleLifecycleEvent;

final class RecordingHook implements ModuleLifecycleHook
{
    /**
     * @var list<string>
     */
    public static array $events = [];

    public function handle(ModuleLifecycleEvent $event): void
    {
        self::$events[] = $event->module.':'.$event->stage->value.($event->pages === [] ? '' : ':'.count($event->pages));
    }
}
