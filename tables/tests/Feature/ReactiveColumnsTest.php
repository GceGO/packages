<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Exceptions\EvaluationException;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Patching\SchemaPatch;
use Gcego\Data\Source;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Export\CsvWriter;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\Table;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Gcego\Tables\Tests\Fixtures\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function registerCourses(Table $table): void
{
    app(Registry::class)->register(new Blueprint('courses', new Ability('viewAny', Subject::class), new Source(Subject::class), $table));
}

function coursesTable(): Table
{
    return Table::make()->columns([
        Text::make('name')->sortable()->tooltip(fn (Subject $record): string => 'Code '.$record->code),
        Text::make('summary')->state(fn (Subject $record): string => $record->name.' ('.$record->code.')'),
        Text::make('credits')->formatStateUsing(fn (mixed $state, Subject $record): string => $state === null ? 'n/a' : $state.' cr'),
        Text::make('code')->visible(fn (?User $user): bool => $user?->name === 'Admin'),
    ])->build();
}

function coursesCsv(): string
{
    $stream = fopen('php://memory', 'w+b');
    app(CsvWriter::class)->write(tables()->export('courses'), $stream);
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('builds tables Filament-style with make()', function (): void {
    $table = coursesTable();

    expect($table->validate()->isValid())->toBeTrue()
        ->and(tables()->schema($table)->columnNames())->toBe(['name', 'summary', 'credits', 'code']);
});

it('evaluates per-row closures with the record injected by type', function (): void {
    registerCourses(coursesTable());

    $html = Livewire::test(TableHost::class, ['definition' => 'courses'])->html();

    expect($html)->toContain('title="Code MTH101"')
        ->and($html)->toContain('Algebra (MTH101)')
        ->and($html)->toContain('3 cr')
        ->and($html)->toContain('n/a');
});

it('removes columns whose visible() closure fails for the user, everywhere', function (): void {
    registerCourses(coursesTable());
    $resolved = tables()->resolve('courses');

    expect(tables()->access($resolved->schema)->readable)->toBe(['name', 'summary', 'credits'])
        ->and(Livewire::test(TableHost::class, ['definition' => 'courses'])->html())->not->toContain('data-gcego-cell="code"')
        ->and(coursesCsv())->not->toContain('MTH101,');

    actAs($this->world['admin']);

    expect(tables()->access(tables()->resolve('courses')->schema)->readable)->toContain('code');
});

it('exports computed and formatted values as plain text', function (): void {
    registerCourses(coursesTable());

    expect(coursesCsv())->toContain('"Algebra (MTH101)"')
        ->and(coursesCsv())->toContain('3 cr');
});

it('checks per-row closure results against the property definition', function (): void {
    $column = Text::make('name')->limit(fn (Subject $record): string => $record->code)->build();

    expect(fn () => $column->forRow('Algebra', $this->world['subjects']['algebra']))->toThrow(EvaluationException::class, 'Expected: int');
});

it('refuses closures for properties that shape the query, and unsearchable computed columns', function (string $column): void {
    $columns = [
        'sortable closure' => Text::make('name')->sortable(fn (): bool => true),
        'searchable computed' => Text::make('summary')->state(fn (): string => 'x')->searchable(),
        'sortable computed' => Text::make('summary')->state(fn (): string => 'x')->sortable(),
    ];

    expect(fn () => Table::make()->columns([$columns[$column]])->buildValidated())->toThrow(InvalidComponentException::class);
})->with(['sortable closure', 'searchable computed', 'sortable computed']);

it('lets other modules extend a blueprint table through Components::extend()', function (): void {
    Components::extend('courses.table', fn (SchemaPatch $patch): SchemaPatch => $patch
        ->insertAfter('name', Text::make('status'))
        ->modify('name', fn (ComponentBuilder $name): ComponentBuilder => $name->label('Course')));
    registerCourses(coursesTable());

    $resolved = tables()->resolve('courses');
    $html = Livewire::test(TableHost::class, ['definition' => 'courses'])->html();

    expect($resolved->schema->columnNames())->toBe(['name', 'status', 'summary', 'credits', 'code'])
        ->and($html)->toContain('Course')
        ->and($html)->toContain('data-gcego-cell="status"');
});
