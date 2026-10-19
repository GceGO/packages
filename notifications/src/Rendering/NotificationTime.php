<?php

declare(strict_types=1);

namespace Gcego\Notifications\Rendering;

use Carbon\CarbonImmutable;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Timestamps as the reader expects them: stored in UTC-consistent
 * application time, shown relative ("5 minutes ago") with the absolute
 * time, in the reader's timezone (the configured user attribute), else
 * the application's.
 */
final readonly class NotificationTime
{
    public function __construct(private NotificationsConfig $config) {}

    public function timezone(?Model $reader): string
    {
        $timezone = $reader?->getAttribute($this->config->timezoneAttribute);
        $default = config('app.timezone');

        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : (is_string($default) ? $default : 'UTC');
    }

    public function html(CarbonImmutable $at, ?Model $reader): HtmlString
    {
        $local = $at->setTimezone($this->timezone($reader))->locale(app()->getLocale());
        $local = $local instanceof CarbonImmutable ? $local : $at;

        return new HtmlString(sprintf('<time datetime="%s" title="%s">%s</time>', e($at->toIso8601String()), e($local->isoFormat('LLL')), e($local->diffForHumans())));
    }
}
