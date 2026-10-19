<?php

declare(strict_types=1);

namespace Gcego\Analytics\Registry;

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\Report;
use Gcego\Registry\Contracts\RegistryInterface;
use Throwable;

/**
 * Registered analytics definitions by name (validated by the registry
 * before any lookup). Unknown names are "not found"; names never reach
 * the registry unless they match the name pattern.
 */
final readonly class AnalyticsDefinitions
{
    public function __construct(private RegistryInterface $registry) {}

    public function dataset(string $name): Dataset
    {
        $dataset = $this->find(AnalyticsKind::Dataset, $name);

        return $dataset instanceof Dataset ? $dataset : throw AnalyticsNotFoundException::make('dataset');
    }

    public function metric(string $name): Metric
    {
        $metric = $this->find(AnalyticsKind::Metric, $name);

        return $metric instanceof Metric ? $metric : throw AnalyticsNotFoundException::make('metric');
    }

    public function report(string $name): Report
    {
        $report = $this->find(AnalyticsKind::Report, $name);

        return $report instanceof Report ? $report : throw AnalyticsNotFoundException::make('report');
    }

    public function dashboard(string $name): Dashboard
    {
        $dashboard = $this->find(AnalyticsKind::Dashboard, $name);

        return $dashboard instanceof Dashboard ? $dashboard : throw AnalyticsNotFoundException::make('dashboard');
    }

    public function has(string $kind, string $name): bool
    {
        return AnalyticsNode::validName($name) && $this->registry->has($kind.':'.$name);
    }

    /**
     * @return list<string>
     */
    public function names(string $kind): array
    {
        $names = array_map(static fn ($descriptor): string => $descriptor->name(), $this->registry->descriptors($kind));
        sort($names);

        return $names;
    }

    /**
     * The dataset a metric reads: its registered dataset, or the implicit dataset of its model.
     */
    public function datasetOf(Metric $metric): ?Dataset
    {
        if ($metric->kind()->isComposite()) {
            return null;
        }

        return $metric->implicitDataset() ?? ($metric->datasetName() === null ? null : $this->dataset((string) $metric->datasetName()));
    }

    private function find(string $kind, string $name): ?object
    {
        if (! $this->has($kind, $name)) {
            return null;
        }

        try {
            return $this->registry->get($kind.':'.$name);
        } catch (Throwable) {
            return null;
        }
    }
}
