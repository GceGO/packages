<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;

/**
 * A widget value: a static scalar, or a trusted closure evaluated only when
 * the widget renders (never when it is defined or registered):
 *
 *     new Stat('Students', new Value(fn (WidgetDataContext $context) => $context->count(Student::class)))
 *     new Stat('Students', fn () => Student::query()->count())             // shorthand: closures become Value nodes
 *
 * The closure may return a scalar or a WidgetDataResult. Prefer the
 * context's helpers (gcego/data, scoped and authorized) over raw queries.
 */
final readonly class Value implements ConfigurationNode
{
    public function __construct(public Closure|int|float|string|null $value) {}

    /**
     * @return array{value: string|int|float|null}
     */
    public function normalize(): array
    {
        return ['value' => $this->value instanceof Closure ? 'closure' : $this->value];
    }
}
