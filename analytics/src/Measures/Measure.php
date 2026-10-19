<?php

declare(strict_types=1);

namespace Gcego\Analytics\Measures;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;

/**
 * One aggregate in a query: COUNT(*), AVG(score)... $alias names it in results.
 */
final readonly class Measure
{
    public function __construct(public string $alias, public Aggregate $aggregate, public ?string $field = null)
    {
        if (! AnalyticsNode::validColumn($alias)) {
            throw AnalyticsQueryException::rejected('measure', 'A measure needs a simple alias.');
        }

        if ($aggregate->needsField() && $field === null) {
            throw AnalyticsQueryException::rejected('measure', sprintf('%s needs a field.', $aggregate->value));
        }
    }

    public static function count(string $alias = 'value'): self
    {
        return new self($alias, Aggregate::Count);
    }

    public function key(): string
    {
        return $this->alias.'='.$this->aggregate->value.'('.($this->field ?? '*').')';
    }
}
