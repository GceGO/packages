<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Gcego\Data\Aggregation\AggregateResult;
use Gcego\Data\Aggregation\Aggregation;
use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Results\DataResult;
use Gcego\Data\Source;
use Gcego\Security\Tenancy\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a provider may know about the request it serves: the widget, the
 * user and tenant, the selected period, the record (for record widgets),
 * the page (feeds), locale and display zone. Built per request and never
 * serialized.
 *
 * The helpers run through gcego/data as this user (policies, tenant scopes,
 * mandatory constraints), so a provider written with them cannot leak data
 * across users or tenants:
 *
 *     $context->count(Student::class)
 *     $context->aggregate(Payment::class, new Aggregation(AggregateFunction::Sum, 'amount', dateColumn: 'paid_at'))   // uses the period
 *     $context->query(new Source(Invoice::class, new Sort('due_on')), ['per_page' => 5])
 */
final readonly class WidgetDataContext
{
    public function __construct(
        public string $widget,
        public string $path,
        public ?Authenticatable $user,
        public ?Tenant $tenant,
        public ?Period $period,
        public string|int|null $record,
        public int $page,
        public string $locale,
        public string $timezone,
        private DataManager $data,
    ) {}

    /**
     * An aggregate as this user. With a date column and no explicit range, the context's period applies.
     *
     * @param  class-string<Model>|Source  $source
     */
    public function aggregate(string|Source $source, Aggregation $aggregation, ?Period $period = null): AggregateResult
    {
        $period ??= $this->period;

        if ($period !== null && $aggregation->dateColumn !== null && $aggregation->from === null && $aggregation->to === null) {
            $aggregation = $aggregation->between($period->from, $period->to);
        }

        return $this->data->aggregate(is_string($source) ? new Source($source) : $source, $aggregation, null, QueryContext::for($this->user));
    }

    /**
     * A count of the records this user may see.
     *
     * @param  class-string<Model>|Source  $source
     */
    public function count(string|Source $source): int
    {
        $value = $this->aggregate($source, new Aggregation)->value;

        return is_int($value) ? $value : (int) $value;
    }

    /**
     * A page of records as this user.
     *
     * @param  array<string, mixed>  $input
     */
    public function query(Source $source, array $input = []): DataResult
    {
        return $this->data->query($source, $input, QueryContext::for($this->user));
    }

    public function with(string $path): self
    {
        return new self($this->widget, $path, $this->user, $this->tenant, $this->period, $this->record, $this->page, $this->locale, $this->timezone, $this->data);
    }
}
