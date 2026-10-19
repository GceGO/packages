<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

/**
 * A category of notifications (filtering and preferences). Mandatory
 * channels are a trusted application policy: they are delivered whatever
 * the user prefers (for example security alerts by mail).
 */
final readonly class NotificationCategory
{
    public const string IdPattern = '/^[a-z][a-z0-9_-]{0,47}$/';

    /**
     * @param  list<string>  $mandatory
     */
    public function __construct(public string $id, public string $label, public array $mandatory = []) {}

    public function label(): string
    {
        return (string) __($this->label);
    }

    public function isMandatory(string $channel): bool
    {
        return in_array($channel, $this->mandatory, true);
    }
}
