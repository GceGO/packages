<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dimensions;

use Gcego\Analytics\Contracts\DimensionLabels;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Illuminate\Database\Eloquent\Model;

/**
 * A column of the dataset to group, segment and filter by:
 *
 *     new Dimension('level', 'Level', options: ['o' => 'Ordinary Level', 'a' => 'Advanced Level'])
 *     new Dimension('subject_id', 'Subject', labelModel: Subject::class)                 // labels from subjects.name, through gcego/data
 *     new Dimension('status', 'Status', labels: ApplicationStatusLabels::class)
 *     new Dimension('institution_id', 'Institution', new Ability('analytics.cross-institution'))
 *
 * Labels from a model are read through gcego/data as the viewer (tenant
 * scopes and policies apply): keys of records the viewer may not see keep
 * no label. Null keys are grouped as "Not set".
 */
final class Dimension extends AnalyticsNode
{
    public const string Column = 'column';

    /** category, boolean or status. */
    public const string Type = 'type';

    /** Static key => label map (also the only allowed filter values when set). */
    public const string Options = 'options';

    public const string LabelModel = 'label_model';

    public const string LabelColumn = 'label_column';

    public const string Labels = 'labels';

    public const string Filterable = 'filterable';

    public const array Types = ['category', 'boolean', 'status'];

    public function column(): string
    {
        return $this->text(self::Column) ?? $this->nodeName();
    }

    public function dimensionType(): string
    {
        return $this->text(self::Type) ?? 'category';
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = $this->get(self::Options);
        $result = [];

        foreach (is_array($options) ? $options : [] as $key => $label) {
            if (is_scalar($label)) {
                $result[(string) $key] = (string) $label;
            }
        }

        return $result;
    }

    /**
     * @return class-string<Model>|null
     */
    public function labelModel(): ?string
    {
        $model = $this->get(self::LabelModel);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    public function labelColumn(): string
    {
        return $this->text(self::LabelColumn) ?? 'name';
    }

    /**
     * @return class-string<DimensionLabels>|null
     */
    public function labelResolver(): ?string
    {
        $resolver = $this->get(self::Labels);

        return is_string($resolver) && is_subclass_of($resolver, DimensionLabels::class) ? $resolver : null;
    }

    public function isFilterable(): bool
    {
        return $this->get(self::Filterable) !== false;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'column' => self::Column, 'type' => self::Type, 'options' => self::Options, 'labelModel' => self::LabelModel, 'labelColumn' => self::LabelColumn, 'labels' => self::Labels, 'filterable' => self::Filterable];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.dimension')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validColumn(...), ruleDescription: 'a dimension name (letters, digits, underscores)'),
                new PropertyDefinition(self::Column, Type::string(), rule: self::validColumn(...), ruleDescription: 'a column of the dataset\'s table'),
                new PropertyDefinition(self::Type, Type::string(), default: 'category', allowedValues: self::Types),
                new PropertyDefinition(self::Options, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && count($value) <= 500 && array_filter($value, static fn (mixed $label): bool => ! is_scalar($label)) === [], ruleDescription: 'at most 500 key => label pairs'),
                new PropertyDefinition(self::LabelModel, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, Model::class), ruleDescription: 'an Eloquent model class'),
                new PropertyDefinition(self::LabelColumn, Type::string(), rule: self::validColumn(...), ruleDescription: 'a column of the label model'),
                new PropertyDefinition(self::Labels, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, DimensionLabels::class), ruleDescription: 'a class implementing '.DimensionLabels::class),
                new PropertyDefinition(self::Filterable, Type::bool(), default: true),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
