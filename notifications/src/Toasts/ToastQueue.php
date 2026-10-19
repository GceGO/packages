<?php

declare(strict_types=1);

namespace Gcego\Notifications\Toasts;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Toasts waiting for the browser, in the user's session (so they survive
 * a redirect): the next page's ToastHost shows them, or the current
 * Livewire response carries them (ToastResponseHook). Without a session
 * (console, queue, API) there is nobody to show a toast to.
 */
final readonly class ToastQueue
{
    public const string SessionKey = 'gcego-notifications.toasts';

    public const int Limit = 20;

    public function __construct(private Container $container) {}

    public function available(): bool
    {
        return $this->session() !== null;
    }

    public function push(Toast $toast): bool
    {
        $session = $this->session();

        if ($session === null) {
            return false;
        }

        $toasts = $session->get(self::SessionKey, []);
        $toasts = is_array($toasts) ? $toasts : [];
        $toasts[] = $toast->toArray();
        $session->put(self::SessionKey, array_slice($toasts, -self::Limit));

        return true;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function pull(): array
    {
        $toasts = $this->session()?->pull(self::SessionKey, []);

        return is_array($toasts) ? array_values(array_filter($toasts, is_array(...))) : [];
    }

    private function session(): ?Session
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make('request');

        return $request instanceof Request && $request->hasSession() ? $request->session() : null;
    }
}
