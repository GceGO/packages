<?php

declare(strict_types=1);

namespace Gcego\Tables;

use Gcego\Actions\Action;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Sorting\Sort;
use Gcego\Tables\Building\TableBuilder;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Contracts\TableComponent;
use Gcego\Tables\Schema\SchemaCompiler;
use Throwable;

/**
 * A declarative data table, and the "table" part of a blueprint:
 *
 *     new Blueprint('subjects', new Source(Subject::class), new Table(
 *         new Text('name', 'Subject Name', new Property(Text::Searchable, true), new Property(Text::Sortable, true)),
 *         new Text('code', 'Code'),
 *         new Boolean('is_published', 'Published'),
 *         new SelectFilter('status', 'Status', new Property(SelectFilter::Options, [...])),
 *         new Pagination(25),
 *         new Edit(),
 *         new Delete(),
 *     ))
 *
 * Children are columns, filters and gcego/actions actions (placed by their
 * own placement: header, row or bulk). Configuration: Search, Pagination,
 * default Sort nodes and Tailwind slots. A Table is a definition only:
 * constructing it never queries, renders or creates Livewire state. Data
 * comes from the blueprint's Source through gcego/data.
 */
final class Table extends Component implements BlueprintPart
{
    use Makeable;

    /** Allow sorting by several columns (up to gcego-data.sorting.max_fields). */
    public const string MultiSort = 'multi_sort';

    /** Row checkboxes; default: on when the table has bulk actions. */
    public const string Selectable = 'selectable';

    /** comfortable | compact (users can switch; their choice is a preference). */
    public const string Density = 'density';

    /** Keep search, filters, sorting and page in the URL query string (prefix: the table name). */
    public const string UrlState = 'url_state';

    public const string EmptyHeading = 'empty_heading';

    public const string EmptyDescription = 'empty_description';

    /** Let users show, hide and reorder columns. */
    public const string ColumnManager = 'column_manager';

    /** Let users save views (needs gcego-tables.saved_views.enabled). */
    public const string SavedViews = 'saved_views';

    /** Live table: refresh every N seconds (5 to 3600) while the page is visible, with a "Live" indicator. */
    public const string Poll = 'poll';

    /** Offer "Export CSV": the rows the user is looking at (search, filters, sorting, visible readable columns). */
    public const string Exportable = 'exportable';

    /** Styling slots defined by tables (Tailwind::Wrapper, Header and Footer apply too). */
    public const string Toolbar = 'toolbar';

    public const string SearchInput = 'search';

    public const string Filters = 'filters';

    public const string TableElement = 'table';

    public const string TableHead = 'head';

    public const string HeaderCell = 'header-cell';

    public const string TableBody = 'tbody';

    public const string Row = 'row';

    public const string Cell = 'cell';

    public const string Pagination = 'pagination';

    public const string EmptyState = 'empty';

    public const array Densities = ['comfortable', 'compact'];

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Table);
    }

    public function tableName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : 'table';
    }

    public function density(): string
    {
        $density = $this->get(self::Density);

        return is_string($density) && in_array($density, self::Densities, true) ? $density : 'comfortable';
    }

    public static function builderClass(): string
    {
        return TableBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('tables.table')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), default: 'table', rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_]{0,31}$/', $value) === 1, ruleDescription: 'a lowercase name of at most 32 characters'),
                new PropertyDefinition(self::MultiSort, Type::bool(), default: false),
                new PropertyDefinition(self::Selectable, Type::bool()),
                new PropertyDefinition(self::Density, Type::string(), default: 'comfortable', allowedValues: self::Densities),
                new PropertyDefinition(self::UrlState, Type::bool(), default: false),
                new PropertyDefinition(self::EmptyHeading, Type::string()),
                new PropertyDefinition(self::EmptyDescription, Type::string()),
                new PropertyDefinition(self::ColumnManager, Type::bool(), default: true),
                new PropertyDefinition(self::SavedViews, Type::bool(), default: true),
                new PropertyDefinition(self::Exportable, Type::bool(), default: false),
                new PropertyDefinition(self::Poll, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 5 && $value <= 3600, ruleDescription: 'a refresh interval of 5 to 3600 seconds'),
            )
            ->withAllowedChildren([TableComponent::class, Action::class], 1)
            ->withAllowedConfigurations([Search::class, Pagination::class, Sort::class, Tailwind::class])
            ->withConstraint(static function (ComponentInterface $table): ?string {
                if (! $table instanceof self) {
                    return null;
                }

                try {
                    (new SchemaCompiler)->compile($table);
                } catch (Throwable $exception) {
                    return $exception->getMessage();
                }

                return null;
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }
}
