<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests\Fixtures;

use Gcego\Actions\Action;
use Gcego\Actions\BulkDelete;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Actions\Handlers\UpdateRecord;
use Gcego\Actions\Restore;
use Gcego\Actions\View;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Badge;
use Gcego\Tables\Columns\Boolean;
use Gcego\Tables\Columns\Date;
use Gcego\Tables\Columns\DateTime;
use Gcego\Tables\Columns\Link;
use Gcego\Tables\Columns\Money;
use Gcego\Tables\Columns\Number;
use Gcego\Tables\Columns\Progress;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Access;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Filters\BooleanFilter;
use Gcego\Tables\Filters\CustomFilter;
use Gcego\Tables\Filters\DateFilter;
use Gcego\Tables\Filters\DateRangeFilter;
use Gcego\Tables\Filters\FilterQuery;
use Gcego\Tables\Filters\SelectFilter;
use Gcego\Tables\Table;
use Gcego\Tables\Tests\Fixtures\Models\Document;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Illuminate\Database\Eloquent\Builder;

final class Blueprints
{
    public static function table(): Table
    {
        return new Table(
            new Text(
                'name',
                'Subject Name',
                new Property(Text::Searchable, true),
                new Property(Text::Sortable, true),
                new Property(Text::Hideable, false)
            ),
            new Text('code', 'Code', new Property(Text::Searchable, true), new Property(Text::Sortable, true), new Property(Text::Copyable, true)),
            new Text('category.name', 'Category', new Property(Text::Searchable, true)),
            new Text('tags.label', 'Tags'),
            new Number('credits', 'Credits', new Property(Number::Decimals, 1), new Property(Number::Sortable, true)),
            new Money('fee', 'Fee', new Property(Money::Currency, 'XAF'), new Property(Money::Sortable, true)),
            new Boolean('is_published', 'Published'),
            new Badge('status', 'Status', new Property(Badge::Variants, ['published' => 'success', 'draft' => 'warning']), new Property(Badge::Labels, ['published' => 'Published', 'draft' => 'Draft'])),
            new Date('starts_on', 'Starts', new Property(Date::Sortable, true)),
            new DateTime('published_at', 'Published at', new Property(DateTime::Visible, false)),
            new Progress('completion', 'Completion'),
            new Link('website', 'Website', new Property(Link::NewTab, true)),
            new Text('secret_notes', 'Secret notes', new Property(Text::Searchable, true), new Property(Text::Sortable, true), new Access(read: new Permission('subjects.secrets'))),
            new Text('salary_band', 'Band', new Access(read: new Permission('subjects.secrets'), mask: true)),
            new SelectFilter('status', 'Status', new Property(SelectFilter::Options, Status::class)),
            new BooleanFilter('is_published', 'Published'),
            new DateRangeFilter('starts_on', 'Starts'),
            new DateFilter('published_at', 'Published on', new Property(DateFilter::Time, true)),
            new CustomFilter('expensive', 'Expensive', new FilterQuery(fn (Builder $query, bool $on) => $query->where('fee', '>=', 50000))),
            new SelectFilter('category', 'Category', new Property(SelectFilter::Field, 'category_id'), new Property(SelectFilter::Relationship, 'category')),
            new SelectFilter('secret_notes', 'Secret', new Property(SelectFilter::Options, ['x' => 'X'])),
            new Search(placeholder: 'Find a subject'),
            new Property(Table::Exportable, true),
            new Pagination(10, options: [5, 10, 25]),
            new Sort('name'),
            new Create,
            new View,
            new Edit,
            new Delete,
            new Restore,
            new BulkDelete,
            new Action('archive', 'Archive', new Property(Action::Ability, 'archive'), new Property(Action::Record, true), new Property(Action::Placement, 'row'), new Handler(UpdateRecord::class)),
        );
    }

    public static function subjects(): Blueprint
    {
        return new Blueprint('subjects', new Ability('viewAny', Subject::class), new Source(Subject::class), new Form(new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)), new TextInput('code', 'Code')), self::table());
    }

    public static function simple(): Blueprint
    {
        return new Blueprint(
            'simple',
            new Ability('viewAny', Subject::class),
            new Source(Subject::class),
            new Table(
                new Text('name', 'Subject Name'),
                new Text('code', 'Code'),
                new Boolean('is_published', 'Published'),
            )
        );
    }

    public static function documents(): Blueprint
    {
        return new Blueprint('documents', new Ability('viewAny', Document::class), new Source(Document::class), new Table(
            new Text('title', 'Title', new Property(Text::Sortable, true)),
            new BulkDelete,
        ));
    }
}
