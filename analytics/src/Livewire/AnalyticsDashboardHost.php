<?php

declare(strict_types=1);

namespace Gcego\Analytics\Livewire;

use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Cache\AnalyticsCache;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Dashboards\DashboardState;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsException;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\Integration\AnalyticsNotifier;
use Gcego\Analytics\Livewire\Concerns\HandlesAnalyticsErrors;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Support\AnalyticsStyles;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * <livewire:gcego-analytics-dashboard dashboard="academic-overview" />:
 * KPI cards, charts and report sections for a period, comparison and
 * filters kept in the URL. Every request re-resolves and re-authorizes
 * the dashboard and validates the state (unknown values fall back to the
 * defaults with a message); the browser never sends queries.
 */
final class AnalyticsDashboardHost extends Component
{
    use HandlesAnalyticsErrors;

    #[Locked]
    public string $dashboard = '';

    /** Link pattern to a report page; ":report" is replaced (set by the panel extension). */
    #[Locked]
    public ?string $reportLink = null;

    #[Url(except: '')]
    public string $range = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $comparison = '';

    /** @var array<string, mixed> */
    #[Url(except: [])]
    public array $filters = [];

    public ?string $message = null;

    public function mount(string $dashboard, ?string $reportLink = null): void
    {
        $this->dashboard = $dashboard;
        $this->reportLink = $reportLink;
    }

    public function updated(string $property): void
    {
        $this->message = null;
        $this->resetErrorBag();

        if ($property === 'range' && $this->range !== 'custom') {
            $this->from = '';
            $this->to = '';
        }
    }

    public function resetFilters(): void
    {
        $this->reset('range', 'from', 'to', 'comparison', 'filters', 'message');
        $this->resetErrorBag();
    }

    public function refreshData(): void
    {
        $this->resetErrorBag();
        app(AnalyticsCache::class)->refresh(app(AnalyticsManager::class)->context());
        $this->message = (string) __('gcego-analytics::messages.refreshed');
    }

    public function export(mixed $report, mixed $format = 'csv'): void
    {
        $this->resetErrorBag();
        $this->message = null;

        if (! is_string($report) || ! is_string($format)) {
            return;
        }

        try {
            $manager = app(AnalyticsManager::class);
            $context = $manager->context();
            $definition = app(AnalyticsDefinitions::class)->dashboard($this->dashboard);

            if (! in_array($report, array_map(static fn ($section): string => $section->report(), $definition->reports()), true)) {
                throw AnalyticsNotFoundException::make('report');
            }

            $state = $this->state($definition, $context->timezone);
            $target = app(AnalyticsDefinitions::class)->report($report);
            $values = array_intersect_key($state->values, array_flip(array_map(static fn ($filter): string => $filter->nodeName(), $target->filters())));
            $export = app(ExportManager::class)->request($target, new ReportRequest($values, $state->range), $format, $context);
            $this->message = (string) __($export->status === 'completed' ? 'gcego-analytics::messages.export_ready' : 'gcego-analytics::messages.export_started');
            $this->dispatch('gcego-analytics-export', url: $export->status === 'completed' ? AnalyticsNotifier::downloadUrl($export) : null);
        } catch (AnalyticsException $exception) {
            $this->addError('analytics', $this->messageFor($exception));
        }
    }

    public function render(): View
    {
        $manager = app(AnalyticsManager::class);
        $timezone = $manager->context()->timezone;
        $view = null;
        $definition = null;

        try {
            $definition = app(AnalyticsDefinitions::class)->dashboard($this->dashboard);

            try {
                $state = $this->state($definition, $timezone);
            } catch (AnalyticsException $exception) {
                $this->addError('analytics', $this->messageFor($exception));
                $state = DashboardState::fromInput($definition, $timezone);
            }

            $view = $manager->dashboard($this->dashboard, $state);
        } catch (AnalyticsAuthorizationException|AnalyticsNotFoundException) {
            abort(403);
        }

        return view('gcego-analytics::livewire.dashboard', [
            'view' => $view,
            'definition' => $definition,
            'styles' => app(AnalyticsStyles::class),
            'presets' => DateRangePreset::values(),
        ]);
    }

    private function state(Dashboard $definition, string $timezone): DashboardState
    {
        return DashboardState::fromInput($definition, $timezone, $this->range, $this->from, $this->to, $this->comparison, self::cleanFilters($this->filters));
    }
}
