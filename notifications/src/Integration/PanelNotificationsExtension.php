<?php

declare(strict_types=1);

namespace Gcego\Notifications\Integration;

use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Http\PanelInboxController;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Throwable;

/**
 * Notifications in every gcego/panels panel: the bell in the top bar, the
 * toast region, and the inbox at "<panel>/notifications" (route
 * "<namespace>notifications"), behind the panel's own middleware and
 * access checks. A notification shows in each panel its recipient may
 * enter; opening its link is authorized by the destination page.
 */
final readonly class PanelNotificationsExtension implements PanelExtension
{
    public const string RouteName = 'notifications';

    public function __construct(private NotificationsConfig $config, private ViewerResolver $viewers) {}

    public function routes(Router $router, PanelDescriptor $panel): void
    {
        if ($this->config->database) {
            $router->get(self::RouteName, PanelInboxController::class)->name(self::RouteName);
        }
    }

    public function topbar(PanelShell $shell): ?Htmlable
    {
        if (! $this->config->database || $this->viewers->current() === null) {
            return null;
        }

        try {
            $inbox = route($shell->context->routeNamespace.self::RouteName, [], false);
        } catch (Throwable) {
            $inbox = null;
        }

        return new HtmlString((string) Livewire::mount('gcego-notification-center', ['inboxUrl' => $inbox], 'gcego-notification-center-'.$shell->context->name()));
    }

    public function body(PanelShell $shell): ?Htmlable
    {
        return $this->config->toasts ? new HtmlString((string) Livewire::mount('gcego-toasts', [], 'gcego-toasts')) : null;
    }
}
