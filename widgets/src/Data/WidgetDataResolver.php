<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Carbon\CarbonImmutable;
use Closure;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Data\Aggregation\TimeBucket;
use Gcego\Data\DataManager;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Relationships\RelationPathValidator;
use Gcego\Data\Relationships\With;
use Gcego\Data\Security\ScopeRegistry;
use Gcego\Data\Sorting\Sortable;
use Gcego\Data\Source;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Widgets\Activity\ActivityFeed;
use Gcego\Widgets\Activity\ActivityItem;
use Gcego\Widgets\Activity\ActivitySource;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Exceptions\InvalidWidgetException;
use Gcego\Widgets\Exceptions\WidgetDataException;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Produces a widget's data when (and only when) it renders:
 *
 * - Value: a static scalar, or a trusted closure (called with the context);
 * - DataProvider: the provider class from the container;
 * - Aggregate: gcego/data's DataManager::aggregate() as the current user
 *   (blueprint sources also check the blueprint's requirements), over the
 *   selected period, with the previous period for trends and zero-filled
 *   time buckets for charts.
 *
 * With a Cache node (and cache.enabled), results are cached under keys that
 * always contain the widget, child, period, page, record and tenant, plus
 * the guard and user for "user" scope. Authorization is checked by the
 * caller before any cached value is used.
 */
final readonly class WidgetDataResolver
{
    public function __construct(private Container $container, private WidgetsConfig $config) {}

    public function resolve(Widget $widget, WidgetDataContext $context, ?WidgetDataResult $shared = null): WidgetDataResult
    {
        $source = $widget->dataSource();

        if ($source === null) {
            return $shared?->for($widget->widgetName()) ?? WidgetDataResult::empty();
        }

        $cache = $widget->cache();

        if ($cache === null || ! $this->config->cacheEnabled) {
            return $this->compute($widget, $source, $context);
        }

        $store = $this->container->make(CacheFactory::class)->store($this->config->cacheStore);
        $version = $store->get(self::versionKey($context->widget), 0);
        $key = $this->cacheKey($context, $cache->scope, is_int($version) ? $version : 0);
        $cached = $store->get($key);

        if ($cached instanceof WidgetDataResult) {
            return $cached;
        }

        $result = $this->compute($widget, $source, $context);
        $store->put($key, $result, $cache->ttl ?? $this->config->cacheTtl);

        return $result;
    }

    /**
     * Invalidates every cached result of a widget identity.
     */
    public function forget(string $identity): void
    {
        $store = $this->container->make(CacheFactory::class)->store($this->config->cacheStore);
        $version = $store->get(self::versionKey($identity), 0);
        $store->forever(self::versionKey($identity), (is_int($version) ? $version : 0) + 1);
    }

    public function cacheKey(WidgetDataContext $context, string $scope, int $version): string
    {
        $security = $this->container->make(SecurityManager::class)->context();

        return 'gcego-widgets:data:'.hash('sha256', (string) json_encode([
            $context->widget,
            $context->path,
            $version,
            $context->period?->toArray(),
            $context->page,
            $context->record,
            $context->tenant === null ? null : $context->tenant->type.':'.$context->tenant->key,
            $scope === 'user' ? [$security->guard, $security->userKey()] : 'tenant',
            $context->locale,
        ]));
    }

    private function compute(Widget $widget, Value|DataProvider|Aggregate|ActivitySource $source, WidgetDataContext $context): WidgetDataResult
    {
        return match (true) {
            $source instanceof Value => $this->value($widget, $source, $context),
            $source instanceof DataProvider => $this->provider($widget, $source, $context),
            $source instanceof ActivitySource => $this->activity($widget, $source, $context),
            default => $this->aggregate($widget, $source, $context),
        };
    }

    /**
     * Newest-first records as activity items, a bounded page at a time, through gcego/data as the current user.
     */
    private function activity(Widget $widget, ActivitySource $node, WidgetDataContext $context): WidgetDataResult
    {
        $data = $this->container->make(DataManager::class);
        $limit = $widget instanceof ActivityFeed ? $widget->limit($this->config->activityDefaultLimit, $this->config->activityMaxLimit) : $this->config->activityDefaultLimit;
        $source = $this->sourceFor($node->source, []);
        $definition = $data->definition($source);
        $nodes = $source->configurations()->all();

        if (! isset($definition->sortables['activity_time'])) {
            $nodes[] = Sortable::map(['activity_time' => $node->time]);
        }

        $relations = [];

        foreach ($node->relations() as $path) {
            if ($definition->model !== null) {
                try {
                    $relations[$path] = $this->scoped(RelationPathValidator::assert('widget', $definition->model, $path));
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if ($relations !== []) {
            $nodes[] = new With($relations);
        }

        if (! $source->has(Source::Pagination) && ! $source->configurations()->has(Paginate::class)) {
            $nodes[] = Paginate::simple($limit, max: $this->config->activityMaxLimit);
        }

        $result = $data->query($source->withConfigurations(...$nodes), ['sort' => '-activity_time', 'per_page' => $limit, 'page' => $context->page], QueryContext::for($context->user));
        $items = [];

        foreach ($result->items() as $record) {
            $title = Formats::text(ValueResolver::get($record, $node->title));
            $at = Formats::moment(ValueResolver::get($record, $node->time));
            $items[] = new ActivityItem(
                title: $title ?? (string) __('gcego-widgets::messages.untitled'),
                description: $node->description === null ? null : Formats::text(ValueResolver::get($record, $node->description)),
                at: $at,
                actor: $node->actor === null ? null : Formats::text(ValueResolver::get($record, $node->actor)),
                icon: $node->icon,
                url: $this->url($node, $record),
                badge: $node->badge === null ? null : Formats::text(ValueResolver::get($record, $node->badge)),
                key: $record instanceof Model && is_scalar($record->getKey()) ? (string) $record->getKey() : null,
            );
        }

        return WidgetDataResult::items($items, $result->pagination->hasMorePages);
    }

    private function url(ActivitySource $node, mixed $record): ?string
    {
        if ($node->route === null) {
            return null;
        }

        $parameters = [];

        foreach ($node->parameters === [] ? ['id' => 'id'] : $node->parameters as $parameter => $path) {
            $value = ValueResolver::get($record, $path);

            if (! is_scalar($value) || $value === '') {
                return null;
            }

            $parameters[$parameter] = $value;
        }

        try {
            return route($node->route, $node->parameters === [] ? array_values($parameters) : $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * An eager-load constraint applying the related model's registered data scopes (tenancy).
     *
     * @param  class-string<Model>  $related
     */
    private function scoped(string $related): Closure
    {
        $data = $this->container->make(DataManager::class);
        $scopes = $this->container->make(ScopeRegistry::class);

        return static function (mixed $query) use ($related, $data, $scopes): void {
            $builder = $query instanceof Relation ? $query->getQuery() : $query;

            if (! $builder instanceof Builder) {
                return;
            }

            $definition = $data->definition(new Source($related));

            foreach ($scopes->for($definition) as $scope) {
                $scope->apply($builder, $definition);
            }
        };
    }

    private function value(Widget $widget, Value $value, WidgetDataContext $context): WidgetDataResult
    {
        $result = $value->value instanceof Closure ? ($value->value)($context) : $value->value;

        return $result instanceof WidgetDataResult ? $result : WidgetDataResult::value(is_scalar($result) || $result === null ? $result : throw WidgetDataException::invalid($widget->widgetName(), 'a Value closure must return a scalar or a WidgetDataResult.'));
    }

    private function provider(Widget $widget, DataProvider $node, WidgetDataContext $context): WidgetDataResult
    {
        $provider = $this->container->make($node->provider);

        return $provider instanceof WidgetDataProvider ? $provider->resolve($context) : throw WidgetDataException::invalid($widget->widgetName(), 'the provider is not a WidgetDataProvider.');
    }

    private function aggregate(Widget $widget, Aggregate $node, WidgetDataContext $context): WidgetDataResult
    {
        $data = $this->container->make(DataManager::class);
        $source = $this->source($node);
        $query = QueryContext::for($context->user);
        $result = $data->aggregate($source, $node->aggregation($context->period), null, $query);

        if (! $node->isGrouped()) {
            $previous = null;

            if ($node->compare && $context->period !== null) {
                $previous = $data->aggregate($source, $node->aggregation($context->period->previous()), null, $query)->value;
            }

            return WidgetDataResult::value($result->value, $previous);
        }

        $pairs = $result->pairs();

        if ($node->bucket !== null && $context->period !== null && $node->bucket !== TimeBucket::Week) {
            $pairs = self::fill($pairs, $node->bucket, $context->period);
        }

        $labels = [];

        foreach ($pairs as $key => $value) {
            $labels[self::label((string) $key, $node->bucket)] = $value;
        }

        return WidgetDataResult::series($node->label ?? $widget->title(), $labels);
    }

    /**
     * The aggregate's Source: a model, or a registered blueprint's Source (whose requirements are checked).
     */
    private function source(Aggregate $node): Source
    {
        return $this->sourceFor($node->source, $node->filters);
    }

    /**
     * A model's Source, or a registered blueprint's Source (whose requirements are checked first).
     *
     * @param  list<Filter>  $filters
     */
    private function sourceFor(string $identity, array $filters): Source
    {
        if (str_starts_with($identity, 'blueprint:')) {
            [, $blueprint] = $this->container->make(ActionResolver::class)->blueprint($identity);
            $this->container->make(SecurityManager::class)->definition($blueprint)->authorize();
            $source = $blueprint->part(BlueprintRole::Source);

            if (! $source instanceof Source) {
                throw InvalidWidgetException::invalid('Widget source', sprintf('%s has no Source.', $identity));
            }

            return $filters === [] ? $source : $source->withConfigurations(...$source->configurations()->all(), ...$filters);
        }

        /** @var class-string<Model> $model */
        $model = $identity;

        return new Source($model, ...$filters);
    }

    /**
     * Every bucket of the period, with 0 where the database returned none.
     *
     * @param  array<string, int|float>  $pairs
     * @return array<string, int|float>
     */
    private static function fill(array $pairs, TimeBucket $bucket, Period $period): array
    {
        $filled = [];
        $cursor = $period->from;
        $guard = 0;

        while ($cursor <= $period->to && $guard++ < 5000) {
            $key = match ($bucket) {
                TimeBucket::Month => $cursor->format('Y-m'),
                TimeBucket::Year => $cursor->format('Y'),
                default => $cursor->format('Y-m-d'),
            };
            $filled[$key] = $pairs[$key] ?? 0;
            $cursor = match ($bucket) {
                TimeBucket::Month => $cursor->startOfMonth()->addMonthNoOverflow(),
                TimeBucket::Year => $cursor->startOfYear()->addYear(),
                default => $cursor->startOfDay()->addDay(),
            };
        }

        foreach ($pairs as $key => $value) {
            $filled[(string) $key] ??= $value;
        }

        ksort($filled);
        $strings = [];

        foreach ($filled as $key => $value) {
            $strings[(string) $key] = $value;
        }

        return $strings;
    }

    private static function label(string $key, ?TimeBucket $bucket): string
    {
        $locale = app()->getLocale();

        try {
            $date = match ($bucket) {
                TimeBucket::Day => CarbonImmutable::createFromFormat('!Y-m-d', $key),
                TimeBucket::Month => CarbonImmutable::createFromFormat('!Y-m', $key),
                default => null,
            };

            if (! $date instanceof CarbonImmutable) {
                return $key;
            }

            $localized = $date->locale($locale);

            return ($localized instanceof CarbonImmutable ? $localized : $date)->isoFormat($bucket === TimeBucket::Day ? 'll' : 'MMM YYYY');
        } catch (\Throwable) {
            return $key;
        }
    }

    private static function versionKey(string $identity): string
    {
        return 'gcego-widgets:version:'.hash('sha256', $identity);
    }
}
