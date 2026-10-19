<?php

declare(strict_types=1);

namespace Gcego\Analytics;

use Gcego\Analytics\Exceptions\InvalidDefinitionException;

/**
 * Validated gcego-analytics configuration (a container singleton). Limits
 * can be lowered, never removed; nothing here can switch authorization off.
 */
final readonly class AnalyticsConfig
{
    public function __construct(
        public bool $enabled = true,
        public ?string $timezone = null,
        public int $maxRows = 10000,
        public int $maxChartPoints = 1000,
        public int $maxGroupCardinality = 500,
        public int $maxRangeDays = 1830,
        public int $maxPageSize = 200,
        public ?int $timeoutMs = null,
        public int $minimumGroupSize = 0,
        public bool $cache = true,
        public int $cacheTtl = 900,
        public ?string $cacheStore = null,
        public bool $exports = true,
        public ?string $exportQueue = 'analytics',
        public ?string $exportConnection = null,
        public string $exportDisk = 'local',
        public string $exportDirectory = 'gcego-analytics/exports',
        public int $exportMaxRows = 100000,
        public int $exportSyncRows = 2000,
        public int $exportChunk = 1000,
        public int $exportTtlHours = 24,
        public int $exportMaxConcurrent = 3,
        public bool $schedules = true,
        public bool $scheduleCommand = true,
        public int $scheduleBatch = 50,
        public bool $requireAuthorization = true,
        public bool $enforceTenantScope = true,
        public ?string $logChannel = null,
        public bool $panels = true,
        public bool $workflows = true,
    ) {
        foreach ([
            'queries.max_rows' => [$maxRows, 1, 1_000_000], 'queries.max_chart_points' => [$maxChartPoints, 2, 10_000], 'queries.max_group_cardinality' => [$maxGroupCardinality, 1, 10_000],
            'queries.max_range_days' => [$maxRangeDays, 1, 36_600], 'queries.max_page_size' => [$maxPageSize, 1, 1000], 'privacy.minimum_group_size' => [$minimumGroupSize, 0, 1000],
            'cache.ttl' => [$cacheTtl, 0, 604_800], 'exports.max_rows' => [$exportMaxRows, 1, 10_000_000], 'exports.sync_rows' => [$exportSyncRows, 0, 100_000],
            'exports.chunk' => [$exportChunk, 10, 100_000], 'exports.ttl_hours' => [$exportTtlHours, 1, 720], 'exports.max_concurrent' => [$exportMaxConcurrent, 1, 100], 'scheduled_reports.batch' => [$scheduleBatch, 1, 5000],
        ] as $key => [$value, $min, $max]) {
            if ($value < $min || $value > $max) {
                throw InvalidDefinitionException::invalid('gcego-analytics.'.$key, sprintf('must be between %d and %d.', $min, $max));
            }
        }

        if ($timeoutMs !== null && ($timeoutMs < 100 || $timeoutMs > 600_000)) {
            throw InvalidDefinitionException::invalid('gcego-analytics.queries.timeout_ms', 'must be between 100 and 600000, or null.');
        }

        if ($timezone !== null && ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw InvalidDefinitionException::invalid('gcego-analytics.timezone', 'must be a time zone identifier.');
        }

        if (preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $exportDirectory) !== 1) {
            throw InvalidDefinitionException::invalid('gcego-analytics.exports.directory', 'use a relative path of letters, digits, dashes and underscores.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $get = static fn (string $key, mixed $default): mixed => data_get($config, $key, $default);
        $bool = static fn (string $key, bool $default): bool => is_bool($value = $get($key, $default)) ? $value : throw InvalidDefinitionException::invalid('gcego-analytics.'.$key, 'must be true or false.');
        $int = static fn (string $key, int $default): int => is_int($value = $get($key, $default)) ? $value : throw InvalidDefinitionException::invalid('gcego-analytics.'.$key, 'must be an integer.');
        $nullableInt = static fn (string $key): ?int => is_int($value = $get($key, null)) || $value === null ? $value : throw InvalidDefinitionException::invalid('gcego-analytics.'.$key, 'must be an integer or null.');
        $string = static fn (string $key, string $default): string => is_string($value = $get($key, $default)) && $value !== '' ? $value : throw InvalidDefinitionException::invalid('gcego-analytics.'.$key, 'must be a string.');
        $nullable = static fn (string $key, ?string $default): ?string => is_string($value = $get($key, $default)) || $value === null ? $value : throw InvalidDefinitionException::invalid('gcego-analytics.'.$key, 'must be a string or null.');

        return new self(
            enabled: $bool('enabled', true),
            timezone: $nullable('timezone', null),
            maxRows: $int('queries.max_rows', 10000),
            maxChartPoints: $int('queries.max_chart_points', 1000),
            maxGroupCardinality: $int('queries.max_group_cardinality', 500),
            maxRangeDays: $int('queries.max_range_days', 1830),
            maxPageSize: $int('queries.max_page_size', 200),
            timeoutMs: $nullableInt('queries.timeout_ms'),
            minimumGroupSize: $int('privacy.minimum_group_size', 0),
            cache: $bool('cache.enabled', true),
            cacheTtl: $int('cache.ttl', 900),
            cacheStore: $nullable('cache.store', null),
            exports: $bool('exports.enabled', true),
            exportQueue: $nullable('exports.queue', 'analytics'),
            exportConnection: $nullable('exports.connection', null),
            exportDisk: $string('exports.disk', 'local'),
            exportDirectory: $string('exports.directory', 'gcego-analytics/exports'),
            exportMaxRows: $int('exports.max_rows', 100000),
            exportSyncRows: $int('exports.sync_rows', 2000),
            exportChunk: $int('exports.chunk', 1000),
            exportTtlHours: $int('exports.ttl_hours', 24),
            exportMaxConcurrent: $int('exports.max_concurrent', 3),
            schedules: $bool('scheduled_reports.enabled', true),
            scheduleCommand: $bool('scheduled_reports.schedule', true),
            scheduleBatch: $int('scheduled_reports.batch', 50),
            requireAuthorization: $bool('security.require_authorization', true),
            enforceTenantScope: $bool('security.enforce_tenant_scope', true),
            logChannel: $nullable('observability.log_channel', null),
            panels: $bool('panels.enabled', true),
            workflows: $bool('integrations.workflows', true),
        );
    }

    public function timezone(): string
    {
        $timezone = $this->timezone ?? config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
