<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Panels\PanelManager;
use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Governance\Authority;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Presets\VaultPresets;
use Gcego\Vault\Support\Context;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Support\HolderReference;
use Gcego\Vault\Support\VaultStyles;
use Gcego\Vault\Tenancy\Scope;
use Gcego\Vault\Vault;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Throwable;

/**
 * Shared behavior of the Vault screens.
 *
 * - The screen's permission is checked in boot(), which Livewire runs on the
 *   first render *and on every update request*, so revoked access stops the
 *   next click, not the next page load.
 * - The actor, panel and tenant come from the server (guard, panel context,
 *   verified TenantContext), never from component state.
 * - Records named by the browser (role ids, assignment ids, holder
 *   references) are always looked up again inside the current scope, so a
 *   tampered id from another tenant is simply not found (404).
 * - Every change goes through Vault's governed services; validation and
 *   refusals become visible feedback, never partial writes.
 */
abstract class VaultComponent extends Component
{
    /** @var array{tone: string, message: string}|null */
    public ?array $feedback = null;

    /**
     * The Vault permission this screen requires (null: anyone signed in).
     */
    abstract protected function permission(): ?string;

    public function boot(): void
    {
        $actor = app(Context::class)->actor();

        if ($actor === null) {
            abort(403);
        }

        $permission = $this->permission();

        if ($permission !== null) {
            app(Authority::class)->authorize($actor, $permission, $this->scope());
        }
    }

    protected function permit(string $permission): void
    {
        app(Authority::class)->authorize($this->actor(), $permission, $this->scope());
    }

    public function dismissFeedback(): void
    {
        $this->feedback = null;
    }

    /**
     * The URL of another Vault screen in the current panel.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function url(string $screen, array $parameters = []): string
    {
        try {
            $context = app(PanelManager::class)->current();

            return $context === null ? '#' : route($context->routeNamespace.'vault.'.$screen, $parameters);
        } catch (Throwable) {
            return '#';
        }
    }

    public function personUrl(Model|Holder $holder): ?string
    {
        $references = app(HolderReference::class);
        $reference = $holder instanceof Model ? $references->encode($holder) : $references->forHolder($holder);

        return $reference === null ? null : $this->url('people.show', ['holder' => $reference]);
    }

    public function may(string $permission): bool
    {
        return app(Authority::class)->holds(app(Context::class)->actor(), $permission, $this->scope());
    }

    protected function actor(): Model
    {
        return app(Context::class)->actor() ?? abort(403);
    }

    protected function scope(): string
    {
        return app(Context::class)->tenant();
    }

    protected function scopeLabel(): string
    {
        return Scope::label($this->scope());
    }

    protected function vault(): Vault
    {
        return app(Vault::class);
    }

    /**
     * A role of the current scope, by an id the browser sent (404 when it is not one).
     */
    protected function scopedRole(int|string $id): Role
    {
        return Role::query()->availableIn($this->scope())->whereKey((int) $id)->first() ?? abort(404);
    }

    /**
     * @return Builder<Role>
     */
    protected function roles(): Builder
    {
        return Role::query()->availableIn($this->scope());
    }

    /**
     * Run a governed change; turn validation errors and refusals into feedback.
     */
    protected function attempt(callable $change, string $success, bool $transaction = false): bool
    {
        $this->resetErrorBag();
        $this->feedback = null;

        try {
            $transaction ? (new Role)->getConnection()->transaction(static fn () => $change()) : $change();
            $this->feedback = ['tone' => 'success', 'message' => $success];

            return true;
        } catch (VaultValidationException $exception) {
            $this->addError($exception->field ?? 'vault', $exception->getMessage());
            $this->feedback = ['tone' => 'error', 'message' => $exception->getMessage()];
        } catch (VaultAuthorizationException $exception) {
            if ($transaction) {
                // The refusal's own audit entry was rolled back with the change: record it again.
                app(AuditLog::class)->record(AuditEvent::EscalationBlocked, Holder::of($this->actor()), tenant: $this->scope(), payload: ['rule' => $exception->rule()]);
            }

            $this->feedback = ['tone' => 'error', 'message' => $exception->getMessage()];
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shared(): array
    {
        return ['p' => VaultPresets::class, 's' => app(VaultStyles::class), 'scopeLabel' => $this->scopeLabel(), 'feedback' => $this->feedback];
    }

    protected function clean(?string $value, int $max = 500): ?string
    {
        $value = $value === null ? null : trim(mb_substr($value, 0, $max));

        return $value === '' ? null : $value;
    }
}
