<?php

declare(strict_types=1);

namespace Gcego\Navigation\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Tailwind;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\Presets\NavigationPresets;
use Gcego\Navigation\Resolution\ResolvedNavigation;
use Gcego\Navigation\Resolution\ResolvedNode;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Styling\StyleSlots;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * What navigation views receive as $nav: the resolved navigation and
 * helpers for classes (computed presets merged with the Navigation's slots
 * and a node's own Tailwind node through gcego/chroma), stable ids, icons
 * and the Alpine.js state of interactive parts (self-contained expressions,
 * so no build step and no global scripts).
 */
final class NavigationView
{
    /** @var array<string, string> Merged class lists of this render (most links share them). */
    private array $merged = [];

    /** @var array<string, string> */
    private array $slots = [];

    public function __construct(
        public readonly ResolvedNavigation $navigation,
        public readonly RenderContext $context,
        public readonly NavigationConfig $config,
        private readonly StyleSlots $styles,
        private readonly ChromaManager $styling,
        private readonly Icons $icons,
    ) {}

    /**
     * ` class="…"` of a Navigation slot over computed classes.
     */
    public function class(string $slot, string ...$computed): HtmlString
    {
        return self::attribute($this->merge(trim(implode(' ', $computed)), $this->slot($slot)));
    }

    public function classes(string $slot, string ...$computed): string
    {
        return $this->merge(trim(implode(' ', $computed)), $this->slot($slot));
    }

    /**
     * ` class="…"` of a node part: computed, then the Navigation's slot, then the node's own Tailwind slot.
     */
    public function part(ResolvedNode $node, string $slot, string ...$computed): HtmlString
    {
        return self::attribute($this->partClasses($node, $slot, ...$computed));
    }

    public function partClasses(ResolvedNode $node, string $slot, string ...$computed): string
    {
        $own = $node->component->configurations()->ofClass(Tailwind::class);
        $classes = [$this->classes($slot, ...$computed)];

        // Only nodes with their own Tailwind node pay for a lookup (which also refuses unknown slots in strict mode).
        if ($own !== []) {
            $classes[] = $this->styling->classes($node->component, $slot);
        }

        return $this->merge(...$classes);
    }

    /**
     * Classes of a link: base, active or inactive, then the slots.
     *
     * @param  list<string>  $base
     */
    public function link(ResolvedNode $node, array $base): HtmlString
    {
        $state = $node->active || $node->activeDescendant ? NavigationPresets::ItemActive : NavigationPresets::ItemInactive;
        $stateSlot = $node->active || $node->activeDescendant ? Navigation::ItemActiveSlot : Navigation::ItemInactiveSlot;

        return self::attribute($this->merge(
            $this->partClasses($node, Navigation::ItemSlot, implode(' ', [...$base, ...$state])),
            $this->slot($stateSlot),
        ));
    }

    public function icon(ResolvedNode $node, string $class = 'size-5'): ?HtmlString
    {
        $icon = $node->component->icon();

        if ($icon === null || ! $this->icons->has($icon)) {
            return null;
        }

        return $this->icons->svg($icon, $this->partClasses($node, Tailwind::Icon, $class.' '.implode(' ', $node->isCurrent() ? NavigationPresets::IconActive : NavigationPresets::IconInactive)));
    }

    public function svg(string $icon, string $class): HtmlString
    {
        return $this->icons->svg($icon, $class);
    }

    public function id(string $suffix = ''): string
    {
        return $this->context->domId($suffix);
    }

    public function nodeId(ResolvedNode $node, string $suffix): string
    {
        return $this->context->domId($node->id.'-'.$suffix);
    }

    public function label(): string
    {
        return $this->navigation->label() ?? (string) __('gcego-navigation::messages.navigation');
    }

    /**
     * ` wire:navigate` for internal links when Livewire navigation is enabled.
     */
    public function navigate(ResolvedNode $node): HtmlString
    {
        return new HtmlString($this->config->wireNavigate && $node->type === 'item' && $node->url !== null && ! str_starts_with($node->url, 'mailto:') ? ' wire:navigate' : '');
    }

    public function compactKey(): string
    {
        return 'gcego-navigation:'.$this->navigation->identity().':compact';
    }

    /**
     * The sidebar's Alpine state. compact is remembered per browser. In the icon rail, tip is the
     * link whose tooltip shows: after a short delay on the first hover, then at once while the
     * pointer moves along the rail (warm), and at once on keyboard focus. query filters the links
     * (search()): every word must appear in a link's label or the labels of its groups.
     */
    public function sidebarState(): string
    {
        $key = $this->compactKey();

        return '{ compact: '.Js::from($this->config->compactToggle && $this->config->defaultCompact).', tip: null, warm: false, timer: null, cooling: null, query: \'\', '
            .'init() { if (! '.Js::from($this->config->compactToggle).') { return } try { const stored = localStorage.getItem('.Js::from($key).'); if (stored !== null) { this.compact = stored === \'1\' } } catch (error) {} }, '
            .'toggle() { this.leaveTip(); this.compact = ! this.compact; try { localStorage.setItem('.Js::from($key).', this.compact ? \'1\' : \'0\') } catch (error) {} }, '
            .'hoverTip(id) { if (! this.compact) { return } clearTimeout(this.timer); clearTimeout(this.cooling); '
            .'if (this.warm) { this.tip = id; return } this.timer = setTimeout(() => { this.tip = id; this.warm = true }, 300) }, '
            .'focusTip(id) { if (! this.compact) { return } clearTimeout(this.timer); clearTimeout(this.cooling); this.tip = id; this.warm = true }, '
            .'leaveTip() { clearTimeout(this.timer); this.tip = null; clearTimeout(this.cooling); this.cooling = setTimeout(() => { this.warm = false }, 400) }, '
            .'get searching() { return this.query.trim() !== \'\' }, '
            .'found(keywords) { const words = this.query.trim().toLowerCase().split(/\\s+/).filter((word) => word !== \'\'); return words.every((word) => keywords.includes(word)) }, '
            .'foundAny(list) { return list.some((keywords) => this.found(keywords)) }, '
            .'search() { if (this.compact) { this.toggle() } this.$nextTick(() => { this.$refs.search && this.$refs.search.focus() }) }, '
            .'shortcut(event) { const target = event.target || {}; if (target.isContentEditable || [\'INPUT\', \'TEXTAREA\', \'SELECT\'].includes(target.tagName)) { return } event.preventDefault(); this.search() }, '
            .'go() { const link = Array.from(this.$el.querySelectorAll(\'a[data-gcego-search]\')).find((element) => element.offsetParent !== null); if (link) { link.click() } }, '
            .'clear() { if (this.searching) { this.query = \'\' } else { this.$refs.search.blur() } } }';
    }

    /**
     * @deprecated use sidebarState()
     */
    public function compactState(): string
    {
        return $this->sidebarState();
    }

    /**
     * What search matches for a link: its label and the labels of the groups around it, lowercased.
     *
     * @param  list<string>  $trail  labels of the groups around it
     */
    public function keywords(ResolvedNode $node, array $trail): string
    {
        return mb_strtolower(implode(' ', [...$trail, (string) $node->label]));
    }

    /**
     * The keywords of every link below a node (or of the whole navigation), to show a group, or
     * the empty state, only while something matches.
     *
     * @param  list<ResolvedNode>  $nodes
     * @param  list<string>  $trail
     * @return list<string>
     */
    public function descendantKeywords(array $nodes, array $trail = []): array
    {
        $keywords = [];

        foreach ($nodes as $node) {
            if ($node->isLink()) {
                $keywords[] = $this->keywords($node, $trail);
            } elseif ($node->children !== []) {
                $keywords = [...$keywords, ...$this->descendantKeywords($node->children, [...$trail, (string) $node->label])];
            }
        }

        return $keywords;
    }

    /**
     * Runs right after the compact sidebar is parsed, before the first paint: a
     * remembered compact state is applied at once (data-compact, which every
     * compact style keys on, and open groups) instead of after Alpine starts,
     * so the sidebar never flashes wide. CSP: carries Vite's nonce.
     */
    public function compactPrelude(): HtmlString
    {
        $script = '(function () { try {'
            .' var stored = localStorage.getItem('.Js::from($this->compactKey()).');'
            .' if (! (stored === null ? '.Js::from($this->config->defaultCompact).' : stored === \'1\')) { return }'
            .' var nav = document.getElementById('.Js::from($this->id()).'); if (! nav) { return }'
            .' nav.setAttribute(\'data-compact\', \'true\');'
            .' nav.querySelectorAll(\'[hidden]\').forEach(function (el) {'
            .' if (/! compact/.test(el.getAttribute(\'x-bind:hidden\') || \'\')) { el.removeAttribute(\'hidden\') }'
            .' }) } catch (error) {} })()';

        return new HtmlString(self::script($script));
    }

    /**
     * An inline script with the CSP nonce of the request (Vite::useCspNonce()), if any.
     */
    public static function script(string $body): string
    {
        $nonce = Vite::cspNonce();

        return '<script'.($nonce === null || $nonce === '' ? '' : ' nonce="'.e($nonce).'"').'>'.$body.'</script>';
    }

    public function dropdownState(): string
    {
        return '{ open: false, '
            .'toggle() { this.open ? this.close(true) : this.show() }, '
            .'show(index = null) { this.open = true; if (index !== null) { this.$nextTick(() => this.focusItem(index)) } }, '
            .'close(restore) { if (! this.open) { return } this.open = false; if (restore) { this.$refs.trigger.focus() } }, '
            .'links() { return Array.from(this.$refs.panel.querySelectorAll(\'a[href]\')) }, '
            .'focusItem(index) { this.links().at(index)?.focus() }, '
            .'move(step) { const links = this.links(); if (links.length === 0) { return } const current = links.indexOf(document.activeElement); this.focusItem(current === -1 ? 0 : (current + step + links.length) % links.length) } }';
    }

    public function mobileState(): string
    {
        return '{ open: false, id: '.Js::from($this->navigation->identity()).', '
            .'show() { this.open = true; document.documentElement.classList.add(\'overflow-hidden\'); this.$nextTick(() => { const panel = this.$refs.panel; (panel.querySelector(\'[aria-current=page]\') ?? panel.querySelector(\'a[href], button:not([disabled])\'))?.focus() }) }, '
            .'close() { if (! this.open) { return } this.open = false; document.documentElement.classList.remove(\'overflow-hidden\'); this.$nextTick(() => this.$refs.trigger?.focus()) }, '
            .'toggle() { this.open ? this.close() : this.show() }, '
            .'matches(event) { return ! event.detail || ! event.detail.id || event.detail.id === this.id }, '
            .'trap(event) { const focusable = Array.from(this.$refs.panel.querySelectorAll(\'a[href], button:not([disabled])\')); if (focusable.length === 0) { return } const first = focusable[0]; const last = focusable.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() } else if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() } } }';
    }

    private function merge(string ...$lists): string
    {
        return $this->merged[implode("\0", $lists)] ??= $this->styling->merge(...$lists);
    }

    private function slot(string $slot): string
    {
        return $this->slots[$slot] ??= ($this->styles)($slot);
    }

    private static function attribute(string $classes): HtmlString
    {
        return new HtmlString($classes === '' ? '' : ' class="'.e($classes).'"');
    }
}
