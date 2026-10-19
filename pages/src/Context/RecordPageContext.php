<?php

declare(strict_types=1);

namespace Gcego\Pages\Context;

use Gcego\Pages\Headers\PageHeader;
use Gcego\Pages\Page;
use Gcego\Pages\State\PageState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The context of a record page (View, Edit, or a custom page with Record):
 * the record was found through the page's scoped gcego/data Source (tenant
 * and security scopes applied) and authorized for the page's record ability.
 *
 * The model stays on the server. Children receive only its key (render
 * metadata "record") and resolve and authorize it again themselves.
 */
readonly class RecordPageContext extends PageContext
{
    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        string $identity,
        Page $page,
        ?string $route,
        array $parameters,
        ?Authenticatable $user,
        ?string $tenant,
        string $locale,
        string $timezone,
        string $fingerprint,
        public Model $record,
        public string $key,
        public ?string $label,
        PageState $state = new PageState,
        ?PageHeader $header = null,
    ) {
        parent::__construct($identity, $page, $route, $parameters, $user, $tenant, $locale, $timezone, $fingerprint, $state, $header);
    }

    public static function for(PageContext $context, Model $record, string $key, ?string $label): self
    {
        return new self($context->identity, $context->page, $context->route, $context->parameters, $context->user, $context->tenant, $context->locale, $context->timezone, $context->fingerprint, $record, $key, $label, $context->state, $context->header);
    }

    public function hasRecord(): bool
    {
        return true;
    }

    public function recordKey(): string
    {
        return $this->key;
    }

    public function withHeader(PageHeader $header): self
    {
        return new self($this->identity, $this->page, $this->route, $this->parameters, $this->user, $this->tenant, $this->locale, $this->timezone, $this->fingerprint, $this->record, $this->key, $this->label, $this->state, $header);
    }

    public function withState(PageState $state): self
    {
        return new self($this->identity, $this->page, $this->route, $this->parameters, $this->user, $this->tenant, $this->locale, $this->timezone, $this->fingerprint, $this->record, $this->key, $this->label, $state, $this->header);
    }
}
