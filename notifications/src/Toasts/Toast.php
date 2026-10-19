<?php

declare(strict_types=1);

namespace Gcego\Notifications\Toasts;

use Gcego\Notifications\Definitions\NotificationPayload;

/**
 * A toast as the browser receives it: plain text (rendered with x-text,
 * never as HTML), checked links, and presentation hints.
 */
final readonly class Toast
{
    /**
     * @param  list<array{label: string, url: string, primary: bool, new_tab: bool}>  $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $body,
        public string $type,
        public string $icon,
        public array $actions,
        public int $duration,
        public bool $assertive,
    ) {}

    public static function of(string $id, NotificationPayload $payload, int $duration): self
    {
        return new self($id, $payload->displayTitle(), $payload->displayBody(), $payload->type->value, $payload->icon, $payload->actions, $duration, $payload->isAssertive());
    }

    /**
     * @return array{id: string, title: string, body: string|null, type: string, icon: string, actions: list<array{label: string, url: string, primary: bool, new_tab: bool}>, duration: int, assertive: bool}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'type' => $this->type, 'icon' => $this->icon, 'actions' => $this->actions, 'duration' => $this->duration, 'assertive' => $this->assertive];
    }
}
