<?php

declare(strict_types=1);

namespace Gcego\Settings\Livewire;

use Gcego\Panels\PanelManager;
use Gcego\Settings\History\SettingsHistoryRepository;
use Gcego\Settings\Pages\SettingsPageResolver;
use Gcego\Settings\Pages\SettingsScreen;
use Gcego\Settings\Search\SettingsSearch;
use Gcego\Settings\Support\SettingsStyles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * <livewire:gcego-settings-panel page="general" />: the settings screen of
 * a panel. Navigation lists only the pages the viewer may open here,
 * search covers only the settings they may see (definitions only, never
 * values), and the history shows changes at the page's scope instance,
 * newest first, paginated, with sensitive values redacted.
 */
final class SettingsPanel extends Component
{
    public const array Tabs = ['settings', 'history'];

    /** The open page (null: the first the viewer may open). */
    #[Locked]
    public ?string $page = null;

    /** Link pattern to a page; ":page" is replaced (set by the panel extension). */
    #[Locked]
    public ?string $link = null;

    public string $search = '';

    public string $tab = 'settings';

    public int $historyPage = 1;

    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::Tabs, true)) {
            $this->tab = 'settings';
        }

        $this->historyPage = 1;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
    }

    public function historyNext(): void
    {
        $this->historyPage++;
    }

    public function historyPrevious(): void
    {
        $this->historyPage = max(1, $this->historyPage - 1);
    }

    #[On('gcego-settings-saved')]
    public function refreshHistory(): void
    {
        $this->historyPage = 1;
    }

    public function render(): View
    {
        $viewer = auth()->user();
        $viewer = $viewer instanceof Authenticatable ? $viewer : abort(403);
        $panel = $this->panel();
        $screens = app(SettingsPageResolver::class)->screens($viewer, $panel);
        $active = $this->active($screens);
        $tab = in_array($this->tab, self::Tabs, true) ? $this->tab : 'settings';
        $history = $tab === 'history' && $active !== null ? app(SettingsHistoryRepository::class)->page($active->context, $active->keys(), max(1, $this->historyPage)) : null;
        $labels = [];

        foreach ($active->visible ?? [] as $definition) {
            $labels[$definition->key] = ['label' => $definition->label(), 'sensitive' => $definition->sensitive];
        }

        return view('gcego-settings::livewire.panel', [
            'screens' => $screens,
            'active' => $active,
            'results' => trim($this->search) === '' ? null : app(SettingsSearch::class)->search($this->search, $viewer, $panel),
            'history' => $history,
            'labels' => $labels,
            'tab' => $tab,
            'styles' => app(SettingsStyles::class),
        ]);
    }

    public function linkTo(string $page, ?string $key = null): string
    {
        $url = $this->link === null ? '#gcego-settings-'.$page : str_replace(':page', rawurlencode($page), $this->link);

        return $key === null ? $url : $url.'#gcego-settings-'.$page.'-'.str_replace('_', '-', $key);
    }

    /**
     * @param  list<SettingsScreen>  $screens
     */
    private function active(array $screens): ?SettingsScreen
    {
        if ($this->page === null) {
            return $screens[0] ?? null;
        }

        foreach ($screens as $screen) {
            if ($screen->page->pageName() === $this->page) {
                return $screen;
            }
        }

        abort(404);
    }

    private function panel(): ?string
    {
        try {
            return app()->bound(PanelManager::class) ? app(PanelManager::class)->current()?->name() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
