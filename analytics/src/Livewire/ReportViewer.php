<?php

declare(strict_types=1);

namespace Gcego\Analytics\Livewire;

use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsException;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Exports\ExportWriter;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\Filters\FilterResolver;
use Gcego\Analytics\Integration\AnalyticsNotifier;
use Gcego\Analytics\Livewire\Concerns\HandlesAnalyticsErrors;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportManager;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Support\AnalyticsStyles;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * <livewire:gcego-analytics-report report="subject-performance" />: a
 * report with its filters, period, sorting, server-side pagination,
 * drill-down links (only to reports the viewer may open, filtered by the
 * row's key) and exports. State lives in the URL and is validated on
 * every request.
 */
final class ReportViewer extends Component
{
    use HandlesAnalyticsErrors;

    #[Locked]
    public string $report = '';

    /** Link pattern to a report page; ":report" is replaced (set by the panel extension). */
    #[Locked]
    public ?string $link = null;

    /** @var array<string, mixed> */
    #[Url(except: [])]
    public array $filters = [];

    #[Url(except: '')]
    public string $range = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: '')]
    public string $direction = '';

    #[Url(except: 1)]
    public int $page = 1;

    public ?string $message = null;

    public function mount(string $report, ?string $link = null): void
    {
        $this->report = $report;
        $this->link = $link;
    }

    public function updated(string $property): void
    {
        $this->message = null;
        $this->resetErrorBag();

        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function sortBy(mixed $column): void
    {
        if (! is_string($column)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction !== 'asc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->page = 1;
    }

    public function goToPage(mixed $page): void
    {
        $this->page = is_numeric($page) ? max(1, (int) $page) : 1;
    }

    public function resetFilters(): void
    {
        $this->reset('filters', 'range', 'from', 'to', 'sort', 'direction', 'page', 'message');
        $this->resetErrorBag();
    }

    public function export(mixed $format = 'csv'): void
    {
        $this->resetErrorBag();
        $this->message = null;

        if (! is_string($format)) {
            return;
        }

        try {
            $context = app(AnalyticsManager::class)->context();
            $definition = app(AnalyticsDefinitions::class)->report($this->report);
            $export = app(ExportManager::class)->request($definition, $this->request($definition, $context->timezone), $format, $context);
            $this->message = (string) __($export->status === 'completed' ? 'gcego-analytics::messages.export_ready' : 'gcego-analytics::messages.export_started');
            $this->dispatch('gcego-analytics-export', url: $export->status === 'completed' ? AnalyticsNotifier::downloadUrl($export) : null);
        } catch (AnalyticsException $exception) {
            $this->addError('analytics', $this->messageFor($exception));
        }
    }

    public function render(): View
    {
        $context = app(AnalyticsManager::class)->context();
        $result = null;
        $definition = null;
        $drill = [];
        $exportable = [];

        try {
            $definition = app(AnalyticsDefinitions::class)->report($this->report);
            $dataset = app(ReportManager::class)->authorize($definition, $context);

            try {
                $result = app(ReportManager::class)->run($definition, $this->request($definition, $context->timezone), $context);
            } catch (AnalyticsException $exception) {
                if ($exception instanceof AnalyticsAuthorizationException) {
                    throw $exception;
                }

                $this->addError('analytics', $this->messageFor($exception));
            }

            foreach ($definition->columns() as $column) {
                $target = $column->drillDown();

                if ($target !== null && $this->link !== null && ($drillReport = $this->drillTarget($target, $column->dimension())) !== null && app(ReportManager::class)->allows($drillReport, $context)) {
                    $drill[$column->nodeName()] = ['url' => str_replace(':report', $target, $this->link), 'filter' => $column->dimension(), 'title' => $drillReport->title()];
                }
            }

            if (app(AnalyticsAuthorizer::class)->export($definition, $dataset, $context)) {
                $exportable = array_values(array_filter($definition->formats(), ExportWriter::supports(...)));
            }
        } catch (AnalyticsAuthorizationException|AnalyticsNotFoundException) {
            abort(403);
        }

        return view('gcego-analytics::livewire.report', [
            'definition' => $definition,
            'result' => $result,
            'drill' => $drill,
            'formats' => $exportable,
            'styles' => app(AnalyticsStyles::class),
            'presets' => DateRangePreset::values(),
            'options' => $definition === null ? [] : $this->filterOptions($definition),
        ]);
    }

    private function request(Report $definition, string $timezone): ReportRequest
    {
        $range = FilterResolver::range($this->range, $this->from, $this->to, $timezone, $definition->defaultRange());

        return new ReportRequest(self::cleanFilters(array_filter($this->filters, static fn (mixed $value): bool => $value !== '' && $value !== null && $value !== [])), $range, $this->sort === '' ? null : $this->sort, $this->direction === '' ? null : $this->direction, $this->page, defaultRange: true);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function filterOptions(Report $definition): array
    {
        $options = [];

        try {
            $dataset = app(AnalyticsDefinitions::class)->dataset($definition->datasetName());
        } catch (AnalyticsNotFoundException) {
            return [];
        }

        foreach ($definition->filters() as $filter) {
            $dimension = $dataset->dimension($filter->nodeName());

            if ($dimension !== null) {
                $options[$filter->nodeName()] = $dimension->options();
            }
        }

        return $options;
    }

    private function drillTarget(string $name, string $dimension): ?Report
    {
        try {
            $target = app(AnalyticsDefinitions::class)->report($name);
        } catch (AnalyticsNotFoundException) {
            return null;
        }

        foreach ($target->filters() as $filter) {
            if ($filter->nodeName() === $dimension) {
                return $target;
            }
        }

        return null;
    }
}
