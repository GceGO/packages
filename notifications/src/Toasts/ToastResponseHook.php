<?php

declare(strict_types=1);

namespace Gcego\Notifications\Toasts;

/**
 * Adds waiting toasts to a Livewire update response, as one
 * "gcego-toasts" browser event on the first component. A response that
 * redirects keeps them in the session for the next page instead.
 */
final readonly class ToastResponseHook
{
    public const string Event = 'gcego-toasts';

    public function __construct(private ToastQueue $queue) {}

    /**
     * @param  array<array-key, mixed>  $payload  Livewire's response payload.
     * @return array<array-key, mixed>
     */
    public function __invoke(array $payload): array
    {
        $components = $payload['components'] ?? null;

        if (! is_array($components) || $components === [] || ! $this->queue->available()) {
            return $payload;
        }

        foreach ($components as $component) {
            if (is_array($component) && is_array($component['effects'] ?? null) && isset($component['effects']['redirect'])) {
                return $payload;
            }
        }

        $toasts = $this->queue->pull();

        if ($toasts === []) {
            return $payload;
        }

        $first = array_key_first($components);

        if (! is_array($components[$first] ?? null)) {
            return $payload;
        }

        $effects = is_array($components[$first]['effects'] ?? null) ? $components[$first]['effects'] : [];
        $dispatches = is_array($effects['dispatches'] ?? null) ? $effects['dispatches'] : [];
        $dispatches[] = ['name' => self::Event, 'params' => ['toasts' => $toasts]];
        $effects['dispatches'] = $dispatches;
        $components[$first]['effects'] = $effects;
        $payload['components'] = $components;

        return $payload;
    }
}
