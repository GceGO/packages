<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Filters\ValueType;

/**
 * Yes / no / any:
 *
 *     new BooleanFilter('is_published', 'Published')
 *     new BooleanFilter('archived_at', 'Archived', new Property(BooleanFilter::Filled, true))   // has a value / is empty
 */
class BooleanFilter extends TableFilter
{
    public const string TrueLabel = 'true_label';

    public const string FalseLabel = 'false_label';

    /** Filter on whether the field has a value (not null / null) instead of its boolean value. */
    public const string Filled = 'filled';

    public function view(): string
    {
        return 'gcego-tables::filters.boolean';
    }

    public function filterable(): Filterable
    {
        return $this->get(self::Filled) === true
            ? new Filterable($this->filterName(), [FilterOperator::Null, FilterOperator::NotNull], ValueType::Boolean, $this->field())
            : new Filterable($this->filterName(), FilterOperator::Equals, ValueType::Boolean, $this->field());
    }

    public function normalizeState(mixed $value, ?string $model = null): mixed
    {
        return match (true) {
            $value === null, $value === '' => null,
            $value === true, $value === 1, $value === '1', $value === 'true' => true,
            $value === false, $value === 0, $value === '0', $value === 'false' => false,
            default => throw $this->invalid('must be true, false or empty.'),
        };
    }

    public function queryInput(mixed $state): array
    {
        if (! is_bool($state)) {
            return [];
        }

        if ($this->get(self::Filled) === true) {
            return [$this->filterName() => [$state ? 'not_null' : 'null' => true]];
        }

        return [$this->filterName() => ['eq' => $state]];
    }

    public function indicator(mixed $state, ?string $model = null): ?string
    {
        return is_bool($state) ? (string) __($this->label()).': '.$this->stateLabel($state) : null;
    }

    public function stateLabel(bool $state): string
    {
        $label = $this->stringProperty($state ? self::TrueLabel : self::FalseLabel);

        return (string) __($label ?? ($state ? 'gcego-tables::messages.yes' : 'gcego-tables::messages.no'));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::TrueLabel, Type::string()),
            new PropertyDefinition(self::FalseLabel, Type::string()),
            new PropertyDefinition(self::Filled, Type::bool(), default: false),
        );
    }
}
