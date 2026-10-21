<?php

declare(strict_types=1);

namespace Gcego\Forms\Rendering;

use Gcego\Forms\Schema\FormSchema;
use Gcego\Forms\State\FieldAccess;
use Gcego\Forms\State\FormState;
use Illuminate\Database\Eloquent\Model;

/**
 * What field renderers need while one form renders: state, errors, field
 * access, the Livewire state prefix ("data", or null for static HTML), the
 * current repeater item keys and wizard step.
 */
final readonly class FormRenderFrame
{
    /**
     * @param  array<string, list<string>>  $errors  Concrete state path => messages.
     * @param  list<string>  $keys  Current repeater item keys.
     * @param  class-string<Model>|null  $model
     * @param  list<string>  $live  Field paths rendered live for this state (see FormManager::livePaths()).
     */
    public function __construct(
        public FormSchema $schema,
        public FormState $state,
        public FieldAccess $access,
        public ?string $prefix = null,
        public array $errors = [],
        public array $keys = [],
        public ?int $step = null,
        public ?string $model = null,
        public ?string $idPrefix = null,
        public array $live = [],
    ) {}

    /**
     * Whether a field path (template) is rendered with a live binding.
     */
    public function isLivePath(string $path): bool
    {
        return $this->schema->isLive($path) || in_array($path, $this->live, true);
    }

    /**
     * The DOM id of a field at a concrete path, stable for the host (error summaries link to it).
     */
    public function idFor(string $path): ?string
    {
        return $this->idPrefix === null ? null : $this->idPrefix.'-'.trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($path)), '-');
    }

    public function withKey(string $key): self
    {
        return new self($this->schema, $this->state, $this->access, $this->prefix, $this->errors, [...$this->keys, $key], $this->step, $this->model, $this->idPrefix, $this->live);
    }

    public function binding(string $path): string
    {
        return $this->prefix === null ? $path : $this->prefix.'.'.$path;
    }

    public function isLive(): bool
    {
        return $this->prefix !== null;
    }
}
