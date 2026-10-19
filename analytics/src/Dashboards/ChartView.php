<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

/**
 * A chart ready to render with gcego/widgets' chart script: the adapter
 * module and its JSON-safe configuration, an accessible summary and the
 * same data as table rows.
 *
 * @phpstan-type Row array{label: string, values: list<string>}
 */
final readonly class ChartView
{
    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $series  Dataset labels (table headers).
     * @param  list<array{label: string, values: list<string>}>  $rows
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $module,
        public array $config,
        public string $summary,
        public array $series,
        public array $rows,
        public bool $empty,
        public ?string $error = null,
    ) {}
}
