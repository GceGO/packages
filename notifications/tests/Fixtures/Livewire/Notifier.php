<?php

declare(strict_types=1);

namespace Gcego\Notifications\Tests\Fixtures\Livewire;

use Gcego\Notifications\Notification;
use Livewire\Component;

/**
 * A component whose actions send toasts (as application code would).
 */
final class Notifier extends Component
{
    public function ping(): void
    {
        Notification::success('Subject created successfully.');
        Notification::error('Unable to save the record.');
    }

    public function pingAndLeave(): void
    {
        Notification::info('Your report is being prepared.');
        $this->redirect('/elsewhere');
    }

    public function render(): string
    {
        return '<div>Notifier</div>';
    }
}
