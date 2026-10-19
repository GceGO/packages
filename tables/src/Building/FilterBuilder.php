<?php

declare(strict_types=1);

namespace Gcego\Tables\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Tables\Filters\FilterQuery;

/**
 * The fluent form of a table filter:
 *
 *     SelectFilter::define('status', 'Status')->options(Status::class)->multiple()
 *     BooleanFilter::define('has_account', 'Has an account', field: 'user_id')->filled()
 *     CustomFilter::define('this_week', 'This week')->query(fn (Builder $query, bool $on) => ...)
 */
class FilterBuilder extends ComponentBuilder
{
    /**
     * A trusted constraint: fn (Builder $query, mixed $value): ?Builder.
     */
    public function query(Closure $constraint): static
    {
        return $this->replaceConfiguration(new FilterQuery($constraint));
    }
}
