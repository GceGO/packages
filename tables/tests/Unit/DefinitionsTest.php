<?php

declare(strict_types=1);

use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Tables\Columns\Badge;
use Gcego\Tables\Columns\Boolean;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Columns\Money;
use Gcego\Tables\Columns\Progress;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Format;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Exceptions\InvalidTableException;
use Gcego\Tables\Filters\SelectFilter;
use Gcego\Tables\Schema\SchemaCompiler;
use Gcego\Tables\Table;
use Gcego\Tables\TablesConfig;
use Gcego\Tables\Tests\Fixtures\Blueprints;
use Gcego\Tables\Tests\Fixtures\Status;

it('accepts the documented table definitions', function (Table $table): void {
    expect($table->validate()->isValid())->toBeTrue();
})->with([
    'minimal' => [new Table(new Text('name', 'Subject Name'), new Text('code', 'Code'), new Boolean('is_published', 'Published'))],
    'property syntax' => [new Table(new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'), new Property(Text::Searchable, true), new Property(Text::Sortable, true)))],
    'configuration' => [new Table(new Text('name', 'Subject Name', new Property(Text::Searchable, true)), new Text('code', 'Code'), new Search, new Pagination(25))],
    'actions' => [new Table(new Text('name', 'Subject Name'), new Boolean('is_published', 'Published'), new Edit, new Delete)],
    'full fixture' => [Blueprints::table()],
]);

it('reads column properties from shorthand and Property syntax alike', function (): void {
    $short = new Text('name', 'Subject Name');
    $long = new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'), new Property(Text::Searchable, true), new Property(Text::Sortable, true));

    expect($short->columnName())->toBe('name')
        ->and($short->label())->toBe('Subject Name')
        ->and($long->isSearchable())->toBeTrue()
        ->and($long->searchFields())->toBe(['name'])
        ->and($long->isSortable())->toBeTrue()
        ->and((new Text('category.name'))->label())->toBe('Category Name')
        ->and((new Text('category.name'))->relations())->toBe(['category'])
        ->and((new Text('full_name', new Property(Text::Searchable, ['first_name', 'last_name'])))->searchFields())->toBe(['first_name', 'last_name']);
});

it('rejects invalid definitions', function (Component $component): void {
    expect($component->validate()->isValid())->toBeFalse();
})->with([
    'duplicate columns' => [new Table(new Text('name'), new Text('name'))],
    'duplicate filters' => [new Table(new Text('name'), new SelectFilter('status', new Property(SelectFilter::Options, ['a' => 'A'])), new SelectFilter('status', new Property(SelectFilter::Options, ['b' => 'B'])))],
    'no columns' => [new Table(new Edit)],
    'search without searchable column' => [new Table(new Text('name'), new Search)],
    'cursor without sort' => [new Table(new Text('name'), new Pagination(10, mode: PaginationMode::Cursor))],
    'bad column name' => [new Text('name; drop table')],
    'sortable relation path' => [new Text('category.name', new Property(Text::Sortable, true))],
    'bad alignment' => [new Text('name', new Property(Text::Align, 'middle'))],
    'select filter without options' => [new SelectFilter('status')],
    'bad currency' => [new Money('fee', new Property(Money::Currency, 'francs'))],
    'unsafe icon name' => [new Badge('s', new Property(Badge::Icons, ['a' => '<svg onload=x>']))],
    'progress bounds' => [new Progress('p', new Property(Progress::Max, 0))],
    'non-table child' => [new Table(new Text('name'), new Component)],
]);

it('compiles schemas without queries and keeps definitions immutable', function (): void {
    $queries = queriesDuring(function () use (&$schema, &$table): void {
        $table = Blueprints::table();
        $table->validate();
        $schema = (new SchemaCompiler)->compile($table);
    });
    $column = new Text('name');

    expect($queries)->toBe([])
        ->and(array_keys($schema->sortable()))->toBe(['name', 'code', 'credits', 'fee', 'starts_on', 'secret_notes'])
        ->and($schema->isSearchable())->toBeTrue()
        ->and($schema->pagination?->perPage)->toBe(10)
        ->and($column->withProperty(new Property(Text::Sortable, true))->isSortable())->toBeTrue()
        ->and($column->isSortable())->toBeFalse()
        ->and((string) json_encode($table->normalize()))->not->toContain('Closure');
});

it('throws for unusable definitions at compile time', function (): void {
    expect(fn () => (new SchemaCompiler)->compile(new Table(new Text('a'), new Text('a'))))->toThrow(InvalidTableException::class, 'two columns share this name')
        ->and(fn () => new Pagination(0))->toThrow(InvalidTableException::class)
        ->and(fn () => new Search(debounceMs: 99999))->toThrow(InvalidTableException::class)
        ->and(fn () => new Format('stdClass'))->toThrow(InvalidTableException::class);
});

it('validates configuration', function (): void {
    expect(TablesConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-tables.php'))->toEqual(new TablesConfig)
        ->and(fn () => TablesConfig::fromArray(['pagination' => ['max_per_page' => 5000]]))->toThrow(InvalidTableException::class)
        ->and(fn () => TablesConfig::fromArray(['pagination' => ['default_per_page' => 200]]))->toThrow(InvalidTableException::class)
        ->and(fn () => TablesConfig::fromArray(['storage' => ['views_table' => 'Views; drop']]))->toThrow(InvalidTableException::class)
        ->and(fn () => TablesConfig::fromArray(['selection' => ['max_records' => 0]]))->toThrow(InvalidTableException::class);
});

it('stringifies values safely', function (mixed $value, ?string $expected): void {
    expect(Column::stringify($value))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty' => ['', null],
    'int' => [42, '42'],
    'enum with label' => [Status::Draft, 'Draft'],
    'list' => [['a', null, 'b'], 'a, b'],
    'object' => [new stdClass, null],
]);
