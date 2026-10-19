<?php

declare(strict_types=1);

namespace Gcego\Panels\Shells;

use Gcego\Chroma\ChromaManager;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Menu\UserMenuLink;
use Gcego\Panels\Panel;
use Gcego\Panels\Presets\PanelPresets;
use Gcego\Panels\Switching\PanelLink;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * Everything a panel document renders, resolved on the server for the
 * current user: escaped by the views, never serialized to Livewire.
 */
final class PanelShell
{
    /** @var array<string, string> */
    private array $classes = [];

    /**
     * @param  list<PanelLink>  $switcher
     * @param  list<UserMenuLink>  $userMenu
     * @param  list<string>  $vite
     */
    public function __construct(
        public readonly PanelContext $context,
        public readonly Panel $panel,
        public readonly PanelState $state,
        public readonly string $title,
        public readonly ?Branding $branding,
        public readonly ?string $homeUrl,
        public readonly string $navigation,
        public readonly array $switcher,
        public readonly array $userMenu,
        public readonly array $vite,
        public readonly string $userName,
        public readonly ?string $tenantName,
        private readonly ChromaManager $styling,
        private readonly Icons $icons,
        public readonly ?string $userEmail = null,
        public readonly ?string $userAvatar = null,
    ) {}

    /**
     * Up to two initials of the user's name ("Jane Doe" -> "JD"), for a user without a picture.
     */
    public function userInitials(): string
    {
        $words = preg_split('/[\s._@-]+/u', trim($this->userName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2)));

        return $initials === '' ? '?' : mb_strtoupper($initials);
    }

    public function brandName(): string
    {
        return $this->branding?->brandName() ?? $this->panel->title();
    }

    /**
     * Preset classes merged with the panel's own classes for the slot (Tailwind nodes).
     *
     * @param  list<string>  $preset
     */
    public function classes(string $slot, array $preset, string ...$extra): string
    {
        $computed = trim(implode(' ', [...$preset, ...$extra]));

        return $this->classes[$slot."\0".$computed] ??= ($own = $this->styling->classes($this->panel, $slot, $this->state->theme)) === '' ? $computed : $this->styling->merge($computed, $own);
    }

    /**
     * Merge class lists, later lists winning conflicts (tailwind-merge rules).
     *
     * @param  list<string>  ...$lists
     */
    public function merge(array ...$lists): string
    {
        return $this->styling->merge(...array_map(static fn (array $list): string => implode(' ', $list), $lists));
    }

    public function svg(string $icon, string $class = 'size-5'): HtmlString
    {
        return $this->icons->svg($icon, $class.' shrink-0');
    }

    public function id(string $suffix): string
    {
        return 'gcego-panel-'.$this->context->name().'-'.$suffix;
    }

    public function collapseKey(): string
    {
        return $this->state->storageKey;
    }

    /**
     * The classes of a sidebar the user keeps collapsed, for this layout.
     */
    public function collapsedClasses(): string
    {
        return implode(' ', PanelPresets::SidebarCollapsed[$this->state->layout === 'compact' ? 'compact' : 'sidebar']);
    }

    /**
     * Runs right after the sidebar is parsed, before the first paint: a sidebar
     * the user keeps collapsed is hidden at once instead of after Alpine starts.
     * The wrapper's init() removes these classes; its binding then owns them.
     */
    public function collapsePrelude(): HtmlString
    {
        $classes = $this->collapsedClasses();

        return new HtmlString($this->script('(function () { try {'
            .' if (localStorage.getItem('.Js::from($this->collapseKey()).') !== \'1\') { return }'
            .' var sidebar = document.getElementById('.Js::from($this->id('sidebar')).'); if (! sidebar) { return }'
            .' var added = '.Js::from($classes).'.split(\' \').filter(function (name) { return ! sidebar.classList.contains(name) });'
            .' sidebar.classList.add.apply(sidebar.classList, added); sidebar.dataset.gcegoPreset = added.join(\' \')'
            .' } catch (error) {} })()'));
    }

    /**
     * The wrapper's Alpine state: collapsed is remembered per browser.
     */
    public function collapseState(): string
    {
        return '{ collapsed: false, init() {'
            .' var sidebar = document.getElementById('.Js::from($this->id('sidebar')).');'
            .' if (sidebar && sidebar.dataset.gcegoPreset !== undefined) { sidebar.classList.remove.apply(sidebar.classList, sidebar.dataset.gcegoPreset.split(\' \')); delete sidebar.dataset.gcegoPreset }'
            .' try { this.collapsed = localStorage.getItem('.Js::from($this->collapseKey()).') === \'1\' } catch (e) {} },'
            .' toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('.Js::from($this->collapseKey()).', this.collapsed ? \'1\' : \'0\') } catch (e) {} } }';
    }

    /**
     * The CSP nonce of the request (Vite::useCspNonce()), for the shell's inline scripts.
     */
    public function nonce(): ?string
    {
        $nonce = Vite::cspNonce();

        return $nonce === null || $nonce === '' ? null : $nonce;
    }

    private function script(string $body): string
    {
        $nonce = $this->nonce();

        return '<script'.($nonce === null ? '' : ' nonce="'.e($nonce).'"').'>'.$body.'</script>';
    }

    public function hasSwitcher(): bool
    {
        return count($this->switcher) > 1;
    }
}
