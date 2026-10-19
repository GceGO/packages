<?php

declare(strict_types=1);

namespace Gcego\Notifications\Http;

use Gcego\Panels\PanelManager;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * The inbox page of a panel (after the panel's authentication, tenancy and access checks).
 */
final readonly class PanelInboxController
{
    public function __invoke(PanelManager $panels): Response
    {
        return $panels->respond((string) __('gcego-notifications::messages.inbox'), new HtmlString((string) Livewire::mount('gcego-notification-inbox', [], 'gcego-notification-inbox')));
    }
}
