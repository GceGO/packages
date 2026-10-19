<?php

declare(strict_types=1);

namespace Gcego\Pages\Livewire;

use Gcego\Pages\Actions\PageActionResolver;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Lifecycle\PageLifecycle;
use Gcego\Pages\PageManager;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Presets\PagePresets;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Exceptions\AccessDeniedException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * <livewire:gcego-page page="page:subjects-edit" record="7" />
 *
 * The one Livewire host of every page (pages are definitions, not Livewire
 * classes). Its browser-visible state is the page identity, the record key,
 * the page's route parameters and the tab, all #[Locked]: never a model, a
 * class or a decision. On every request (mount and each update) the page is
 * resolved from the registry, authorized, and its record looked up in scope
 * and authorized again, so a revoked permission, a tenant switch or a
 * deleted record takes effect immediately.
 *
 * The page's tables, forms, infolists, actions and widgets are their own
 * Livewire components (mounted once, keyed by the page identity and their
 * path); this host only listens for their completed actions to follow them
 * (after a save, to the record; after a delete, back to the list) and to
 * refresh the header.
 */
final class PageHost extends Component
{
    #[Locked]
    public string $page = '';

    #[Locked]
    public ?string $record = null;

    /**
     * @var array<string, string>
     */
    #[Locked]
    public array $parameters = [];

    #[Locked]
    public ?string $tab = null;

    /** The record disappeared (deleted, moved out of scope) while the page was open. */
    #[Locked]
    public bool $missing = false;

    private ?PageContext $context = null;

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function mount(string $page, string|int|null $record = null, array $parameters = [], ?string $tab = null): void
    {
        $this->context = app(PageManager::class)->open($page, $record, $parameters, ['tab' => $tab]);
        $this->page = $this->context->identity;
        $this->record = $this->context->recordKey();
        $this->parameters = $this->context->parameters;
        $this->tab = $this->context->state->tab;
    }

    /**
     * Every update re-opens the page: registry, page authorization, scoped record, record authorization.
     */
    public function hydrate(): void
    {
        if ($this->missing) {
            return;
        }

        try {
            $this->context = app(PageLifecycle::class)->open($this->page, $this->record, $this->parameters, ['tab' => $this->tab], interaction: true);
        } catch (AccessDeniedException|NotFoundHttpException $exception) {
            if ($this->record === null || ! self::recordMissing($exception)) {
                throw $exception;
            }

            $this->context = null;
            $this->missing = true;
        }
    }

    /**
     * A page part's action completed ("gcego-action-completed", dispatched by
     * gcego/actions and gcego/forms hosts). The payload is untrusted: it only
     * selects a target the definition allows.
     */
    #[On('gcego-action-completed')]
    public function actionCompleted(mixed $result = null): void
    {
        if ($this->context === null) {
            return;
        }

        $url = app(PageActionResolver::class)->redirectAfter($this->context, $result);

        if ($url !== null) {
            $this->redirect($url, navigate: app(PagesConfig::class)->wireNavigate);
        }
    }

    public function render(): View
    {
        if ($this->context === null) {
            return view('gcego-pages::states.missing', [
                'url' => app(PageActionResolver::class)->indexUrl($this->page, app(PageManager::class)->resolve($this->page), $this->parameters),
                'class' => implode(' ', PagePresets::Empty),
            ]);
        }

        return view('gcego-pages::livewire.host', [
            'content' => app(PageManager::class)->renderContext($this->context, live: true),
            'loading' => new HtmlString(implode(' ', PagePresets::Loading)),
        ]);
    }

    private static function recordMissing(AccessDeniedException|NotFoundHttpException $exception): bool
    {
        return $exception instanceof NotFoundHttpException || $exception->decision()?->reason === DenialReason::NotFound;
    }
}
