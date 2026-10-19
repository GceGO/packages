<?php

declare(strict_types=1);

namespace Gcego\Analytics\Security;

use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\Report;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Auth\Access\Gate;
use Throwable;

/**
 * Who may see and do what, deny by default:
 *
 * - datasets, reports and dashboards need gcego/security requirements
 *   (with security.require_authorization, a definition without any is
 *   denied) and every one must pass, as the context's user in its tenant;
 * - a metric needs its dataset (an implicit dataset: the metric's own
 *   requirements) plus its own requirements; composite metrics need every
 *   metric they use;
 * - sensitive fields and restricted dimensions need their requirements;
 * - exports and schedules need the report, its Export / Schedulable flag
 *   and its ExportAbility when set.
 *
 * Results are memoized per context; errors deny.
 */
final class AnalyticsAuthorizer
{
    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(private readonly SecurityManager $security, private readonly Gate $gate, private readonly AnalyticsConfig $config, private readonly ContextScope $scope) {}

    public function dataset(Dataset $dataset, AnalyticsContext $context, ?Metric $implicitFor = null): bool
    {
        return $implicitFor !== null ? $this->requires($implicitFor, $context, true) : $this->requires($dataset, $context, true);
    }

    public function metric(Metric $metric, ?Dataset $dataset, AnalyticsContext $context): bool
    {
        if ($metric->kind()->isComposite()) {
            return $this->requires($metric, $context, false);
        }

        if ($dataset === null) {
            return false;
        }

        if ($metric->model() !== null) {
            return $this->requires($metric, $context, true);
        }

        return $this->dataset($dataset, $context) && $this->requires($metric, $context, false);
    }

    public function report(Report $report, Dataset $dataset, AnalyticsContext $context): bool
    {
        return $this->requires($report, $context, true) && $this->dataset($dataset, $context);
    }

    public function dashboard(Dashboard $dashboard, AnalyticsContext $context): bool
    {
        return $this->requires($dashboard, $context, true);
    }

    public function field(Field $field, AnalyticsContext $context): bool
    {
        return $field->isSensitive() ? $this->requires($field, $context, true) : $this->requires($field, $context, false);
    }

    public function dimension(Dimension $dimension, AnalyticsContext $context): bool
    {
        return $this->requires($dimension, $context, false);
    }

    public function export(Report $report, Dataset $dataset, AnalyticsContext $context): bool
    {
        if (! $this->config->exports || ! $report->exportable() || ! $this->report($report, $dataset, $context)) {
            return false;
        }

        $ability = $report->exportAbility();

        if ($ability === null) {
            return true;
        }

        try {
            $user = $context->user;

            return $user !== null && $this->gate->forUser($user)->allows($ability, [$report->nodeName()]);
        } catch (Throwable) {
            return false;
        }
    }

    public function schedule(Report $report, Dataset $dataset, AnalyticsContext $context): bool
    {
        return $this->config->schedules && $report->schedulable() && $this->export($report, $dataset, $context);
    }

    /**
     * @param  bool  $mandatory  With require_authorization, a definition without requirements is denied.
     */
    private function requires(AnalyticsNode $definition, AnalyticsContext $context, bool $mandatory): bool
    {
        $requirements = $definition->requirements();

        if ($requirements === []) {
            return ! ($mandatory && $this->config->requireAuthorization);
        }

        $key = $definition::class.':'.$definition->nodeName().':'.spl_object_id($definition).':'.$context->audience();

        return $this->memo[$key] ??= $this->satisfies($requirements, $context);
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    private function satisfies(array $requirements, AnalyticsContext $context): bool
    {
        if ($context->user === null) {
            return false;
        }

        try {
            return $this->scope->run($context, fn (): bool => ! $this->security->check(...$requirements)->denied());
        } catch (Throwable) {
            return false;
        }
    }
}
