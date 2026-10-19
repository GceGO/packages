<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration;

use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Registry\AnalyticsKind;
use Gcego\Analytics\Reports\ReportManager;
use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Throwable;

/**
 * Analytics pages in every gcego/panels panel, behind the panel's
 * authentication, tenancy and access checks (and each definition's own):
 *
 * - "<panel>/analytics/{dashboard}" (route "<namespace>analytics.dashboard");
 * - "<panel>/analytics/reports" — the reports the viewer may open ("<namespace>analytics.reports");
 * - "<panel>/analytics/reports/{report}" ("<namespace>analytics.report").
 *
 * A dashboard cannot be named "reports" (that path lists the reports).
 *
 * Unknown definitions are 404; definitions the viewer may not see are 403.
 */
final readonly class PanelAnalyticsExtension implements PanelExtension
{
    public function __construct(private AnalyticsConfig $config) {}

    public function routes(Router $router, PanelDescriptor $panel): void
    {
        if (! $this->config->panels) {
            return;
        }

        $name = '[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*';

        // Under analytics/ so no application page (e.g. one named "reports") collides; before analytics/{dashboard}, which would match "reports".
        $router->get('analytics/reports', static function (PanelManager $panels, AnalyticsDefinitions $definitions, ReportManager $reports, AnalyticsManager $analytics) {
            $context = $analytics->context();
            $visible = [];

            foreach ($definitions->names(AnalyticsKind::Report) as $name) {
                try {
                    $report = $definitions->report($name);

                    if ($reports->allows($report, $context)) {
                        $visible[] = ['name' => $name, 'title' => $report->title(), 'description' => $report->description()];
                    }
                } catch (Throwable) {
                }
            }

            return $panels->respond((string) __('gcego-analytics::messages.reports'), new HtmlString(view('gcego-analytics::pages.reports', ['reports' => $visible, 'base' => url()->current()])->render()));
        })->name('analytics.reports');

        $router->get('analytics/reports/{report}', static function (PanelManager $panels, AnalyticsDefinitions $definitions, string $report) {
            $definition = self::found(fn () => $definitions->report($report));
            $link = Str::beforeLast(url()->current(), '/reports/').'/reports/:report';

            return $panels->respond($definition->title(), new HtmlString((string) Livewire::mount('gcego-analytics-report', ['report' => $report, 'link' => $link], 'gcego-analytics-report')));
        })->where('report', $name)->name('analytics.report');

        $router->get('analytics/{dashboard}', static function (PanelManager $panels, AnalyticsDefinitions $definitions, string $dashboard) {
            $definition = self::found(fn () => $definitions->dashboard($dashboard));
            $reports = Str::beforeLast(url()->current(), '/analytics/').'/analytics/reports/:report';

            return $panels->respond($definition->title(), new HtmlString((string) Livewire::mount('gcego-analytics-dashboard', ['dashboard' => $dashboard, 'reportLink' => $reports], 'gcego-analytics-dashboard')));
        })->where('dashboard', $name)->name('analytics.dashboard');
    }

    public function topbar(PanelShell $shell): ?Htmlable
    {
        return null;
    }

    public function body(PanelShell $shell): ?Htmlable
    {
        return null;
    }

    /**
     * @template T of AnalyticsNode
     *
     * @param  \Closure(): T  $resolve
     * @return T
     */
    private static function found(\Closure $resolve): AnalyticsNode
    {
        try {
            return $resolve();
        } catch (AnalyticsNotFoundException) {
            abort(404);
        }
    }
}
