<?php

declare(strict_types=1);

namespace Gcego\Infolists\Livewire;

use Gcego\Infolists\InfolistManager;
use Gcego\Security\Exceptions\AccessDeniedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * <livewire:gcego-infolist definition="subjects" :record="$subject->id" />
 *
 * Shows one record of a registered blueprint, with its record actions
 * (gcego/actions placement "record": Edit, Delete, Restore, custom).
 *
 * The definition and record key are #[Locked]. Every request resolves the
 * blueprint through the registry, checks its requirements, finds the record
 * through the scoped source and checks "view" again, and decides entry
 * access for the current user: nothing authorized earlier is reused, and
 * no model or value is kept in the snapshot (only the two keys). After an
 * action the record is re-read; a record that is gone or no longer visible
 * shows an unavailable state.
 */
final class InfolistHost extends Component
{
    #[Locked]
    public string $definition = '';

    #[Locked]
    public string|int $record = '';

    public function mount(string $definition, string|int $record): void
    {
        $infolists = app(InfolistManager::class);
        $resolved = $infolists->resolve($definition);
        $infolists->authorize($resolved);
        $infolists->record($resolved, $record);

        $this->definition = $resolved->definition;
        $this->record = $record;
    }

    public function refresh(): void
    {
        // Re-render: the record is read again with the current user's access.
    }

    /**
     * @param  array<string, mixed>  $result
     */
    #[On('gcego-action-completed')]
    public function actionCompleted(array $result = []): void
    {
        // Re-render with the updated (or deleted) record.
    }

    public function render(): View
    {
        $infolists = app(InfolistManager::class);
        $resolved = $infolists->resolve($this->definition);
        $infolists->authorize($resolved);

        try {
            $record = $infolists->record($resolved, $this->record);
        } catch (AccessDeniedException|AuthorizationException|HttpExceptionInterface) {
            return view('gcego-infolists::infolists.host', ['content' => null]);
        }

        $id = $this->getId();

        return view('gcego-infolists::infolists.host', [
            'content' => $infolists->renderRecord($resolved, $record, is_string($id) ? $id : null, live: true),
        ]);
    }

    public function placeholder(): HtmlString
    {
        return new HtmlString('<div role="status" class="p-6 text-sm text-gcego-text-muted">'.e((string) __('gcego-infolists::messages.loading')).'</div>');
    }
}
