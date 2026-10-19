<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;

/**
 * The trusted constraint of a CustomFilter. It receives the query builder
 * and the filter's validated value; the value is data, never SQL.
 *
 *     new FilterQuery(fn (Builder $query, bool $overdue) => $overdue ? $query->where('due_on', '<', today()) : $query)
 */
final readonly class FilterQuery implements ConfigurationNode
{
    /**
     * @param  Closure  $constraint  fn (Builder $query, mixed $value): ?Builder
     */
    public function __construct(public Closure $constraint) {}

    /**
     * @return array{constraint: string}
     */
    public function normalize(): array
    {
        return ['constraint' => 'closure'];
    }
}
