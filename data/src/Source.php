<?php

declare(strict_types=1);

namespace Gcego\Data;

use Closure;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Property;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Data\Contracts\SourceConfiguration;
use Gcego\Data\Support\Callback;
use Gcego\Data\Support\FieldName;
use Illuminate\Database\Eloquent\Model;

/**
 * A declarative data-source definition, and the "source" part of a blueprint.
 *
 *     new Source(Subject::class);
 *
 *     new Source(new Property(Source::Model, Subject::class), new Property(Source::Pagination, 25));
 *
 *     new Source(
 *         Subject::class,
 *         new Filter('is_published', true),
 *         new Sort('name', 'asc'),
 *         new Paginate(25),
 *     );
 *
 *     Source::table('report_rows', new Sortable('total'));
 *     Source::collection(fn () => config('school.terms'));
 *
 * A Source only describes data. Constructing one never touches the database;
 * queries run when DataManager (or a package built on it) executes it.
 * Like every component it is immutable.
 */
final class Source extends Component implements BlueprintPart
{
    /** Eloquent model class (the positional shorthand). */
    public const string Model = 'model';

    /** Provider name: "eloquent", "query", "collection" or a registered custom provider. Inferred when omitted. */
    public const string Provider = 'provider';

    /** Database table of a query-builder source. */
    public const string Table = 'table';

    /** Database connection name. Eloquent sources default to the model's connection. */
    public const string Connection = 'connection';

    /** Unique key of a query-builder or collection source (default "id"). Eloquent uses the model's key. */
    public const string Key = 'key';

    /** Columns selected by a query-builder source (default all). */
    public const string Columns = 'columns';

    /** Trusted factory (Callback) returning a query builder, for query-builder sources. */
    public const string Builder = 'builder';

    /** Trusted factory (Callback) returning the items of a collection source. */
    public const string Items = 'items';

    /** Trusted constraint (Callback), equivalent to a Query node. */
    public const string Query = 'query';

    /** Fixed equality conditions: ['is_published' => true], equivalent to Filter nodes. */
    public const string Filters = 'filters';

    /** Searchable fields, equivalent to a Search node. */
    public const string Search = 'search';

    /** Default ordering: ['name', '-created_at'], equivalent to Sort nodes. */
    public const string Sort = 'sort';

    /** Page size, equivalent to new Paginate($size). */
    public const string Pagination = 'pagination';

    /** Always eager-loaded relations, equivalent to a With node. */
    public const string Relationships = 'relationships';

    /**
     * @param  class-string<Model>  $model
     */
    public static function model(string $model, Property|ConfigurationNode ...$items): self
    {
        return new self($model, ...$items);
    }

    public static function table(string $table, Property|ConfigurationNode ...$items): self
    {
        return new self(new Property(self::Table, $table), ...$items);
    }

    /**
     * A query-builder source from a trusted factory, called for every execution:
     *
     *     Source::builder(fn () => DB::table('enrolments')->join(...)->select(...), new Property(Source::Key, 'enrolments.id'))
     */
    public static function builder(Closure $factory, Property|ConfigurationNode ...$items): self
    {
        return new self(new Property(self::Builder, new Callback($factory)), ...$items);
    }

    /**
     * An in-memory source. Suitable for small, bounded datasets: every execution loads all items.
     *
     * @param  Closure(): iterable<mixed>|iterable<mixed>  $items
     */
    public static function collection(Closure|iterable $items, Property|ConfigurationNode ...$configuration): self
    {
        $factory = $items instanceof Closure ? $items : static fn (): iterable => $items;

        return new self(new Property(self::Items, new Callback($factory)), ...$configuration);
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Source);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $field = static fn (mixed $value): bool => is_string($value) && FieldName::isValid($value);
        $fields = static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $item): bool => ! $field($item)) === [];

        return parent::defineSchema()
            ->withType('data.source')
            ->withProperties(
                new PropertyDefinition(self::Model, Type::classString(Model::class), description: 'Eloquent model class.'),
                new PropertyDefinition(self::Provider, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $value) === 1, ruleDescription: 'provider name'),
                new PropertyDefinition(self::Table, Type::string(), rule: $field, ruleDescription: 'table name'),
                new PropertyDefinition(self::Connection, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && $value !== '', ruleDescription: 'connection name'),
                new PropertyDefinition(self::Key, Type::string(), rule: $field, ruleDescription: 'column name'),
                new PropertyDefinition(self::Columns, Type::list(), rule: $fields, ruleDescription: 'non-empty list of column names'),
                new PropertyDefinition(self::Builder, Type::instanceOf(Callback::class)),
                new PropertyDefinition(self::Items, Type::instanceOf(Callback::class)),
                new PropertyDefinition(self::Query, Type::instanceOf(Callback::class)),
                new PropertyDefinition(self::Filters, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && ! array_is_list($value), ruleDescription: 'map of field => value'),
                new PropertyDefinition(self::Search, Type::list(), rule: $fields, ruleDescription: 'non-empty list of field names'),
                new PropertyDefinition(self::Sort, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $item): bool => ! is_string($item) || ! $field(ltrim($item, '-'))) === [], ruleDescription: 'list of fields, "-" prefix for descending'),
                new PropertyDefinition(self::Pagination, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1, ruleDescription: 'positive page size'),
                new PropertyDefinition(self::Relationships, Type::list(), rule: $fields, ruleDescription: 'non-empty list of relation paths'),
            )
            ->withoutChildren()
            ->withAllowedConfigurations([SourceConfiguration::class])
            ->withConstraint(static function (ComponentInterface $source): ?string {
                if (! $source instanceof self) {
                    return null;
                }

                $origins = array_values(array_filter([self::Model, self::Table, self::Builder, self::Items], $source->has(...)));
                $custom = $source->has(self::Provider) && ! in_array($source->get(self::Provider), ['eloquent', 'query', 'collection'], true);

                return match (true) {
                    count($origins) > 1 => sprintf('declare only one of model, table, builder or items (found %s).', implode(', ', $origins)),
                    $origins === [] && ! $custom => 'declare a model class (new Source(Subject::class)), a table, a builder or items.',
                    default => null,
                };
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Model];
    }
}
