<?php

declare(strict_types=1);

namespace Gcego\Notifications\Livewire;

use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Definitions\NotificationType;
use Gcego\Notifications\Notification;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Notifications\Presets\NotificationPresets;
use Gcego\Notifications\Rendering\NotificationStyles;
use Gcego\Notifications\Toasts\ToastQueue;
use Gcego\Notifications\Toasts\ToastResponseHook;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * <livewire:gcego-toasts />: the page's toast region (panels add it to
 * every panel document). It renders the toasts waiting in the session;
 * later ones arrive as browser events from Livewire responses
 * ("gcego-toasts"), gcego/actions results ("gcego-action-completed") and,
 * with broadcasting, the user's private channel. Toasts live in the
 * browser after that: dismissing or expiring one needs no request.
 *
 * Only one host renders per page (a second one renders nothing), and the
 * browser side registers its listeners once.
 *
 * Icons are rendered on the server by gcego/luminaries (so alias overrides
 * apply) into <template data-gcego-toast-icon> elements; the browser clones
 * them by name and never turns toast data into HTML. A toast whose icon was
 * not rendered shows its type's icon.
 */
final class ToastHost extends Component
{
    /** @var list<array<array-key, mixed>> */
    protected array $initial = [];

    protected bool $duplicate = false;

    public function mount(): void
    {
        $this->duplicate = app(ToastHostRegistry::class)->claimed();
        $this->initial = $this->duplicate ? [] : app(ToastQueue::class)->pull();
    }

    public function render(): View
    {
        $config = app(NotificationsConfig::class);
        $styles = app(NotificationStyles::class);
        $viewer = app(ViewerResolver::class)->current();
        $typeIcons = [];

        foreach (NotificationType::cases() as $type) {
            $typeIcons[$type->value] = $type->icon();
        }

        $icons = [];

        foreach ([...array_values($typeIcons), ...array_column($this->initial, 'icon')] as $icon) {
            if (is_string($icon) && ! isset($icons[$icon])) {
                $svg = $styles->svg($icon, $styles->classes(Notification::ToastIconSlot, NotificationPresets::ToastIcon))->toHtml();

                if ($svg !== '') {
                    $icons[$icon] = $svg;
                }
            }
        }

        return view('gcego-notifications::toasts.host', [
            'duplicate' => $this->duplicate,
            'config' => $config,
            'styles' => $styles,
            'icons' => $icons,
            'state' => [
                'initial' => $this->initial,
                'max' => $config->maxVisible,
                'typeIcons' => $typeIcons,
                'actions' => $config->actionResultToasts,
                'duration' => $config->duration,
                'errorDuration' => $config->errorDuration,
                'channel' => $config->broadcasting && $viewer !== null ? app(NotificationChannels::class)->for($viewer->user) : null,
                'tenant' => $viewer?->tenant,
                'panel' => $viewer?->panel,
                'event' => ToastResponseHook::Event,
            ],
        ]);
    }
}
