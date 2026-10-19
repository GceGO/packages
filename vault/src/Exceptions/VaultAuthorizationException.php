<?php

declare(strict_types=1);

namespace Gcego\Vault\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * A Vault operation the actor may not perform: missing authority,
 * privilege escalation, self-promotion, a foreign tenant, a protected role's
 * last holder, self-approval. A Laravel AuthorizationException, so it renders
 * as 403 and Livewire, handlers and tests treat it as an authorization failure.
 *
 * The message is public and generic enough to reveal no policy internals;
 * code() names the rule for logs, audits and diagnostics.
 */
final class VaultAuthorizationException extends AuthorizationException implements VaultException
{
    private string $rule = 'denied';

    public static function because(string $rule, array $replace = []): self
    {
        $exception = new self((string) __('gcego-vault::messages.denials.'.$rule, $replace));
        $exception->rule = $rule;
        $exception->withStatus(403);

        return $exception;
    }

    public function rule(): string
    {
        return $this->rule;
    }
}
