<?php

declare(strict_types=1);

namespace Gcego\Notifications\Database;

use Gcego\Notifications\Delivery\Recipients;
use Illuminate\Database\Eloquent\Model;

/**
 * Whose notifications a query reads: the signed-in user, in the current
 * tenant and panel. Built on the server (ViewerResolver), never from
 * request input.
 */
final readonly class NotificationViewer
{
    public function __construct(
        public Model $user,
        public ?string $tenant = null,
        public ?string $panel = null,
    ) {}

    public function type(): string
    {
        return $this->user->getMorphClass();
    }

    public function key(): string
    {
        return substr(Recipients::reference($this->user), strlen($this->type()) + 1);
    }

    public function reference(): string
    {
        return Recipients::reference($this->user);
    }
}
