<?php

declare(strict_types=1);

namespace Gcego\Pages\Context;

use Gcego\Pages\Headers\PageHeader;
use Gcego\Pages\Page;
use Gcego\Pages\State\PageState;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Everything known about one open page in one request, after the page was
 * authorized: identity, definition, route, untrusted-but-validated route
 * parameters, the security principal and tenant, locale and timezone.
 *
 * Built by PageLifecycle for each request (Livewire updates included) and
 * never stored in singletons, so nothing leaks between requests or users.
 * It is server-side only: Livewire hosts keep the identity and keys, never
 * this object.
 */
readonly class PageContext
{
    /**
     * @param  array<string, string>  $parameters  Route parameters other than {record}.
     */
    public function __construct(
        public string $identity,
        public Page $page,
        public ?string $route,
        public array $parameters,
        public ?Authenticatable $user,
        public ?string $tenant,
        public string $locale,
        public string $timezone,
        public string $fingerprint,
        public PageState $state = new PageState,
        public ?PageHeader $header = null,
    ) {}

    public function type(): string
    {
        return $this->page::pageType();
    }

    public function name(): string
    {
        return $this->page->pageName();
    }

    public function hasRecord(): bool
    {
        return false;
    }

    public function recordKey(): ?string
    {
        return null;
    }

    public function parameter(string $name): ?string
    {
        return $this->parameters[$name] ?? null;
    }

    public function withHeader(PageHeader $header): self
    {
        return new self($this->identity, $this->page, $this->route, $this->parameters, $this->user, $this->tenant, $this->locale, $this->timezone, $this->fingerprint, $this->state, $header);
    }

    public function withState(PageState $state): self
    {
        return new self($this->identity, $this->page, $this->route, $this->parameters, $this->user, $this->tenant, $this->locale, $this->timezone, $this->fingerprint, $state, $this->header);
    }
}
