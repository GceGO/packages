<?php

declare(strict_types=1);

namespace Gcego\Widgets\Activity;

use DateTimeInterface;

/**
 * One entry of an activity feed or recent-records list. Plain values only:
 * everything is escaped when rendered and URLs are checked (SafeUrl).
 */
final readonly class ActivityItem
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?DateTimeInterface $at = null,
        public ?string $actor = null,
        public ?string $icon = null,
        public ?string $url = null,
        public ?string $badge = null,
        public string $tone = 'neutral',
        public ?string $key = null,
    ) {}
}
