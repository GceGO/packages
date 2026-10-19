<?php

declare(strict_types=1);

namespace Gcego\Settings\Security;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who writes: a person (authorized on every write) or a named system actor
 * chosen explicitly by trusted code ($settings->asSystem('seeder')), for
 * seeders, deployments, commands and applied approvals. A system actor
 * skips person authorization, never validation, scope rules or history.
 * Browser input can never produce one.
 */
final readonly class SettingsActor
{
    private function __construct(public ?Authenticatable $user, public ?string $system) {}

    public static function user(Authenticatable $user): self
    {
        return new self($user, null);
    }

    public static function system(string $name = 'system'): self
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,47}$/', $name) !== 1) {
            throw InvalidSettingException::definition('A system actor', 'needs a lowercase name such as "seeder".');
        }

        return new self(null, $name);
    }

    public function isSystem(): bool
    {
        return $this->system !== null;
    }

    public function type(): ?string
    {
        if ($this->user === null) {
            return null;
        }

        return $this->user instanceof Model ? $this->user->getMorphClass() : $this->user::class;
    }

    public function id(): ?string
    {
        return $this->user === null ? null : mb_substr((string) AuthorizationContext::keyOf($this->user), 0, 64);
    }

    public function name(): ?string
    {
        return $this->system;
    }

    /**
     * "type#id" of a person, or "system:<name>" (stored as updated_by).
     */
    public function reference(): string
    {
        return $this->user === null ? 'system:'.$this->system : mb_substr($this->type().'#'.$this->id(), 0, 191);
    }
}
