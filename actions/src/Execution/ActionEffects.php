<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Closure;

/**
 * UI-independent effects a handler asks for, collected during one execution:
 *
 *     $context->effects->redirect(route('subjects.show', $subject));
 *     $context->effects->afterCommit(fn () => Mail::to(...)->send(...));
 *
 * afterCommit() callbacks run only after the outermost transaction commits
 * (never after a rollback), so irreversible side effects stay out of transactions.
 */
final class ActionEffects
{
    private ?string $redirect = null;

    private bool $refresh = true;

    private bool $closeDialog = true;

    private ?string $message = null;

    /**
     * @var list<Closure(): mixed>
     */
    private array $afterCommit = [];

    public function redirect(string $url): self
    {
        $this->redirect = $url;

        return $this;
    }

    public function withoutRefresh(): self
    {
        $this->refresh = false;

        return $this;
    }

    public function keepDialogOpen(): self
    {
        $this->closeDialog = false;

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function afterCommit(Closure $callback): self
    {
        $this->afterCommit[] = $callback;

        return $this;
    }

    /**
     * @return list<Closure(): mixed>
     */
    public function pendingAfterCommit(): array
    {
        return $this->afterCommit;
    }

    public function redirectTo(): ?string
    {
        return $this->redirect;
    }

    public function shouldRefresh(): bool
    {
        return $this->refresh;
    }

    public function shouldCloseDialog(): bool
    {
        return $this->closeDialog;
    }

    public function customMessage(): ?string
    {
        return $this->message;
    }
}
