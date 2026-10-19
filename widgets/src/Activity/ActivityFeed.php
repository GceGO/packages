<?php

declare(strict_types=1);

namespace Gcego\Widgets\Activity;

use Carbon\CarbonImmutable;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DataProvider;
use Gcego\Widgets\Data\Value;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

/**
 * A bounded, newest-first list of events:
 *
 *     new ActivityFeed('Recent activity', new ActivitySource(ActivityLog::class, title: 'description', time: 'created_at', actor: 'causer.name'))
 *     new ActivityFeed('Admissions', new DataProvider(AdmissionsFeed::class), new Property(ActivityFeed::Limit, 5))
 *
 * At most activity.max_limit items per page; with Pages, "Older" and
 * "Newer" (Livewire) move through bounded pages. Providers return WidgetDataResult::items().
 * gcego/widgets does not record activity: it presents existing records.
 */
class ActivityFeed extends Widget
{
    public const string Limit = 'limit';

    /** Offer "Newer" / "Older" pages (needs Livewire; the widget becomes interactive). */
    public const string Pages = 'pages';

    public function view(): string
    {
        return 'gcego-widgets::activity.feed';
    }

    public function source(): ?ActivitySource
    {
        $source = $this->configurations()->first(ActivitySource::class);

        return $source instanceof ActivitySource ? $source : null;
    }

    public function dataSource(): Value|DataProvider|Aggregate|ActivitySource|null
    {
        return $this->source() ?? parent::dataSource();
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        return [
            'items' => array_map(static fn (ActivityItem $item): array => [
                'title' => $item->title,
                'description' => $item->description,
                'at' => $item->at === null ? null : CarbonImmutable::instance($item->at)->setTimezone($config->timezone ?? (is_string($zone = config('app.timezone')) ? $zone : 'UTC')),
                'actor' => $item->actor,
                'icon' => $item->icon,
                'url' => $item->url === null ? null : SafeUrl::link($item->url),
                'badge' => $item->badge,
                'tone' => in_array($item->tone, ['default', 'success', 'warning', 'danger', 'info', 'neutral'], true) ? $item->tone : 'neutral',
                'key' => $item->key,
            ], $result->items),
            'hasMore' => $result->hasMore && $this->get(self::Pages) === true && $frame->live,
            'hasPrevious' => $frame->state->page > 1 && $this->get(self::Pages) === true && $frame->live,
            'empty' => $result->items === [],
        ];
    }

    public function limit(int $default, int $max): int
    {
        $limit = $this->get(self::Limit);

        return max(1, min($max, is_int($limit) ? $limit : $default));
    }

    public function needsLivewire(): bool
    {
        return $this->get(self::Pages) === true || parent::needsLivewire();
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Limit, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 200, ruleDescription: 'between 1 and 200'),
                new PropertyDefinition(self::Pages, Type::bool(), default: false),
            )
            ->withAllowedConfigurations([...self::widgetConfigurations(), ActivitySource::class]);
    }
}
