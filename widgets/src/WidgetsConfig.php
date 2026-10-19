<?php

declare(strict_types=1);

namespace Gcego\Widgets;

use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * Validated gcego-widgets configuration (config/gcego-widgets.php).
 */
final readonly class WidgetsConfig
{
    public function __construct(
        public string $chartAdapter = 'chartjs',
        public int $minimumRefreshSeconds = 30,
        public bool $cacheEnabled = false,
        public ?string $cacheStore = null,
        public int $cacheTtl = 300,
        public int $activityDefaultLimit = 10,
        public int $activityMaxLimit = 50,
        public int $maxChartPoints = 500,
        public int $maxDatasets = 10,
        public int $maxRangeDays = 732,
        public ?string $timezone = null,
        public string $currency = 'XAF',
    ) {
        $check = static function (bool $valid, string $key, string $reason): void {
            if (! $valid) {
                throw InvalidWidgetException::invalid('gcego-widgets.'.$key, $reason);
            }
        };

        $check(preg_match('/^[a-z][a-z0-9-]{0,31}$/', $chartAdapter) === 1, 'charts.default_adapter', 'use an adapter id such as "chartjs".');
        $check($minimumRefreshSeconds >= 5 && $minimumRefreshSeconds <= 3600, 'refresh.minimum_interval_seconds', 'use 5 to 3600.');
        $check($cacheTtl >= 1 && $cacheTtl <= 86400, 'cache.ttl', 'use 1 to 86400 seconds.');
        $check($activityDefaultLimit >= 1 && $activityDefaultLimit <= $activityMaxLimit, 'activity.default_limit', 'use 1 to activity.max_limit.');
        $check($activityMaxLimit >= 1 && $activityMaxLimit <= 200, 'activity.max_limit', 'use 1 to 200.');
        $check($maxChartPoints >= 2 && $maxChartPoints <= 5000, 'charts.max_points', 'use 2 to 5000.');
        $check($maxDatasets >= 1 && $maxDatasets <= 20, 'charts.max_datasets', 'use 1 to 20.');
        $check($maxRangeDays >= 1 && $maxRangeDays <= 3660, 'dates.max_range_days', 'use 1 to 3660.');
        $check($timezone === null || in_array($timezone, timezone_identifiers_list(), true), 'dates.timezone', 'use a valid timezone identifier or null.');
        $check(preg_match('/^[A-Z]{3}$/', $currency) === 1, 'formatting.currency', 'use an ISO 4217 code such as "XAF".');
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $int = static function (string $key, int $default) use ($config): int {
            $value = data_get($config, $key, $default);

            return is_int($value) ? $value : throw InvalidWidgetException::invalid('gcego-widgets.'.$key, 'it must be an integer.');
        };
        $string = static function (string $key, ?string $default) use ($config): ?string {
            $value = data_get($config, $key, $default);

            return $value === null || is_string($value) ? $value : throw InvalidWidgetException::invalid('gcego-widgets.'.$key, 'it must be a string.');
        };
        $enabled = data_get($config, 'cache.enabled', false);

        return new self(
            chartAdapter: $string('charts.default_adapter', 'chartjs') ?? 'chartjs',
            minimumRefreshSeconds: $int('refresh.minimum_interval_seconds', 30),
            cacheEnabled: is_bool($enabled) ? $enabled : throw InvalidWidgetException::invalid('gcego-widgets.cache.enabled', 'it must be a boolean.'),
            cacheStore: $string('cache.store', null),
            cacheTtl: $int('cache.ttl', 300),
            activityDefaultLimit: $int('activity.default_limit', 10),
            activityMaxLimit: $int('activity.max_limit', 50),
            maxChartPoints: $int('charts.max_points', 500),
            maxDatasets: $int('charts.max_datasets', 10),
            maxRangeDays: $int('dates.max_range_days', 732),
            timezone: $string('dates.timezone', null),
            currency: $string('formatting.currency', 'XAF') ?? 'XAF',
        );
    }
}
