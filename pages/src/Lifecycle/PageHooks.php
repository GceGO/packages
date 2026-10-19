<?php

declare(strict_types=1);

namespace Gcego\Pages\Lifecycle;

use Gcego\Pages\Contracts\PageLifecycleHook;
use Gcego\Pages\Exceptions\InvalidPageException;
use Gcego\Pages\PagesConfig;
use Illuminate\Contracts\Container\Container;

/**
 * The lifecycle hooks of the application: gcego-pages.lifecycle.hooks plus
 * those added with PageManager::hook() (a singleton; hooks are trusted
 * classes, resolved through the container for each event, so they hold no
 * per-request state between requests).
 */
final class PageHooks
{
    /**
     * @var list<class-string<PageLifecycleHook>>
     */
    private array $hooks;

    public function __construct(private readonly Container $container, PagesConfig $config)
    {
        $this->hooks = $config->hooks;
    }

    /**
     * @param  class-string<PageLifecycleHook>  $hook
     */
    public function add(string $hook): void
    {
        if (! is_subclass_of($hook, PageLifecycleHook::class)) {
            throw InvalidPageException::invalid('Lifecycle hook '.$hook, 'it must implement '.PageLifecycleHook::class.'.');
        }

        if (! in_array($hook, $this->hooks, true)) {
            $this->hooks[] = $hook;
        }
    }

    /**
     * @return list<class-string<PageLifecycleHook>>
     */
    public function all(): array
    {
        return $this->hooks;
    }

    public function fire(PageLifecycleEvent $event): void
    {
        foreach ($this->hooks as $hook) {
            $instance = $this->container->make($hook);

            if ($instance instanceof PageLifecycleHook) {
                $instance->handle($event);
            }
        }
    }
}
