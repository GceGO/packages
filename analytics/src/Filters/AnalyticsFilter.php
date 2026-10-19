<?php

declare(strict_types=1);

namespace Gcego\Analytics\Filters;

/**
 * A condition on a dataset field or dimension. Untrusted input becomes one
 * only through FilterResolver (validated against the schema); $trusted
 * marks conditions written in definitions (metric filters).
 */
final readonly class AnalyticsFilter
{
    /**
     * @param  scalar|list<scalar>|null  $value
     */
    public function __construct(
        public string $target,
        public FilterOperator $operator,
        public string|int|float|bool|array|null $value = null,
        public bool $trusted = false,
    ) {}

    public function key(): string
    {
        return $this->target.':'.$this->operator->value.':'.json_encode($this->value);
    }
}
