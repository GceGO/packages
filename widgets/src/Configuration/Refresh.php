<?php

declare(strict_types=1);

namespace Gcego\Widgets\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * How a widget refreshes. Without this node, widgets refresh only on demand
 * (the refresh button, a date-range change) and never poll.
 *
 *     new Refresh(every: 60)                                    // poll every minute while the widget is visible
 *     new Refresh(on: ['gcego-action-completed'])               // refresh when these browser events fire
 *
 * Polling uses Livewire's wire:poll with .visible, so hidden tabs and
 * off-screen widgets do not poll, and Livewire never overlaps requests of
 * one component. Intervals below refresh.minimum_interval_seconds are refused.
 * Every refresh re-authorizes and re-reads the data.
 */
final readonly class Refresh implements ConfigurationNode
{
    public const string EventPattern = '/^[a-z][a-z0-9:._-]{0,63}$/';

    /**
     * @param  list<string>  $on
     */
    public function __construct(public ?int $every = null, public array $on = [])
    {
        if ($every !== null && ($every < 5 || $every > 86400)) {
            throw InvalidWidgetException::invalid('Refresh', 'every must be between 5 and 86400 seconds.');
        }

        foreach ($on as $event) {
            if (! is_string($event) || preg_match(self::EventPattern, $event) !== 1) {
                throw InvalidWidgetException::invalid('Refresh', 'events must be lowercase names such as "gcego-action-completed".');
            }
        }
    }

    public function polls(): bool
    {
        return $this->every !== null;
    }

    /**
     * @return array{every: int|null, on: list<string>}
     */
    public function normalize(): array
    {
        return ['every' => $this->every, 'on' => $this->on];
    }
}
