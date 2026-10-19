<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Carbon\CarbonImmutable;

/**
 * A resolved date range: inclusive instants in the storage (application)
 * time zone, computed from day boundaries in the display time zone.
 */
final readonly class Period
{
    public function __construct(
        public string $preset,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $displayZone,
    ) {}

    /**
     * The period of the same length immediately before this one (for trends).
     */
    public function previous(): self
    {
        $seconds = $this->to->getTimestamp() - $this->from->getTimestamp() + 1;

        return new self('previous', $this->from->subSeconds($seconds), $this->from->subSecond(), $this->displayZone);
    }

    public function days(): int
    {
        return (int) $this->from->setTimezone($this->displayZone)->startOfDay()->diffInDays($this->to->setTimezone($this->displayZone)->startOfDay()) + 1;
    }

    /**
     * @return array{preset: string, from: string, to: string}
     */
    public function toArray(): array
    {
        return ['preset' => $this->preset, 'from' => $this->from->setTimezone($this->displayZone)->format('Y-m-d'), 'to' => $this->to->setTimezone($this->displayZone)->format('Y-m-d')];
    }
}
