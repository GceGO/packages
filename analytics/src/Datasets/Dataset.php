<?php

declare(strict_types=1);

namespace Gcego\Analytics\Datasets;

use Gcego\Analytics\Contracts\AnalyticsProvider;
use Gcego\Analytics\Contracts\AnalyticsScope;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Illuminate\Database\Eloquent\Model;

/**
 * An analytical source: what may be measured, grouped and filtered, and by whom.
 *
 *     return new Dataset('assessment-results', AssessmentResult::class,
 *         new Ability('analytics.assessments'),
 *         new Field('score', 'Score', type: 'number'),
 *         new Field('submitted_at', type: 'datetime'),
 *         new Dimension('subject_id', 'Subject', labelModel: Subject::class),
 *         new Dimension('level', 'Level', options: ['o' => 'Ordinary Level', 'a' => 'Advanced Level']),
 *         dateColumn: 'submitted_at',
 *         minimumGroupSize: 5,
 *     );
 *
 * Only declared fields and dimensions are ever queried (never "every
 * column that exists"). Eloquent datasets run through gcego/data as the
 * viewer: the model's policy (viewAny), global scopes, soft deletes and
 * tenant scopes apply before any aggregation, then the dataset's Scope
 * (record level). A custom Provider must apply them itself.
 * minimumGroupSize hides groups smaller than that (small-cell suppression).
 * Registry kind "dataset".
 */
final class Dataset extends AnalyticsNode
{
    public const string Model = 'model';

    public const string Provider = 'provider';

    /** The default date column of time ranges and series. */
    public const string DateColumn = 'date_column';

    /** Groups with fewer rows than this are suppressed (privacy). 0 or 1 disables it. */
    public const string MinimumGroupSize = 'minimum_group_size';

    /** An AnalyticsScope narrowing rows to what the viewer may see. */
    public const string Scope = 'scope';

    /**
     * @return class-string<Model>|null
     */
    public function model(): ?string
    {
        $model = $this->get(self::Model);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    /**
     * @return class-string<AnalyticsProvider>|null
     */
    public function provider(): ?string
    {
        $provider = $this->get(self::Provider);

        return is_string($provider) && is_subclass_of($provider, AnalyticsProvider::class) ? $provider : null;
    }

    public function dateColumn(): ?string
    {
        return $this->text(self::DateColumn);
    }

    public function minimumGroupSize(): ?int
    {
        return $this->integer(self::MinimumGroupSize);
    }

    /**
     * @return class-string<AnalyticsScope>|null
     */
    public function scope(): ?string
    {
        $scope = $this->get(self::Scope);

        return is_string($scope) && is_subclass_of($scope, AnalyticsScope::class) ? $scope : null;
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return $this->childrenOf(Field::class);
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields() as $field) {
            if ($field->nodeName() === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<Dimension>
     */
    public function dimensions(): array
    {
        return $this->childrenOf(Dimension::class);
    }

    public function dimension(string $name): ?Dimension
    {
        foreach ($this->dimensions() as $dimension) {
            if ($dimension->nodeName() === $name) {
                return $dimension;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if (($this->model() === null) === ($this->provider() === null)) {
            $problems[] = 'a dataset needs exactly one of Model (an Eloquent model) or Provider (an AnalyticsProvider).';
        }

        $names = [...array_map(static fn (Field $field): string => $field->nodeName(), $this->fields()), ...array_map(static fn (Dimension $dimension): string => $dimension->nodeName(), $this->dimensions())];

        foreach (array_count_values($names) as $name => $count) {
            if ($count > 1) {
                $problems[] = sprintf('"%s" is declared more than once (fields and dimensions share names).', $name);
            }
        }

        $date = $this->dateColumn();

        if ($date !== null && ($field = $this->field($date)) !== null && ! $field->fieldType()->isTemporal()) {
            $problems[] = sprintf('the date column "%s" is declared as a %s field.', $date, $field->fieldType()->value);
        }

        foreach ($this->fields() as $field) {
            if ($field->aggregates() === []) {
                $problems[] = sprintf('the field "%s" allows no aggregate its type supports.', $field->nodeName());
            }
        }

        return $problems;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'model' => self::Model, 'provider' => self::Provider, 'dateColumn' => self::DateColumn, 'minimumGroupSize' => self::MinimumGroupSize, 'scope' => self::Scope];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.dataset')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "assessment-results"'),
                new PropertyDefinition(self::Model, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, Model::class), ruleDescription: 'an Eloquent model class'),
                new PropertyDefinition(self::Provider, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, AnalyticsProvider::class), ruleDescription: 'a class implementing '.AnalyticsProvider::class),
                new PropertyDefinition(self::DateColumn, Type::string(), rule: self::validColumn(...), ruleDescription: 'a column of the dataset\'s table'),
                new PropertyDefinition(self::MinimumGroupSize, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 1000, ruleDescription: 'between 0 and 1000'),
                new PropertyDefinition(self::Scope, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, AnalyticsScope::class), ruleDescription: 'a class implementing '.AnalyticsScope::class),
            )
            ->withAllowedChildren([Field::class, Dimension::class])
            ->withConstraint(static fn (ComponentInterface $dataset): array => $dataset instanceof self ? $dataset->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Model];
    }
}
