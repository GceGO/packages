<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Layout;
use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Layouts\Tests\Fixtures\Providers\CountProvider;
use Gcego\Registry\Registry;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Widgets\Activity\ActivityFeed;
use Gcego\Widgets\Activity\ActivitySource;
use Gcego\Widgets\Charts\Chart;
use Gcego\Widgets\Data\DataProvider;
use Gcego\Widgets\Statistics\Stat;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('runs the documented developer examples as written', function (): void {
    $view = new Ability('viewAny', Student::class);
    $examples = [
        'grid' => new Grid(
            new Stat('Students', CountProvider::class),
            new Stat('Teachers', CountProvider::class),
            new Stat('Subjects', CountProvider::class),
        ),
        'columns' => new Grid(
            new Columns(3),
            new Stat('Students', CountProvider::class),
            new Stat('Teachers', CountProvider::class),
            new Stat('Subjects', CountProvider::class),
        ),
        'dashboard' => new Layout(
            new Section(
                'Overview',
                new Grid(
                    new Columns(3),
                    new Stat('Students', CountProvider::class),
                    new Stat('Teachers', CountProvider::class),
                    new Stat('Subjects', CountProvider::class),
                ),
            ),
            new Section(
                'Enrolment Trends',
                new Chart(
                    new Property(Chart::Name, 'enrolment'),
                    new DataProvider(CountProvider::class),
                ),
            ),
        ),
        'tailwind' => new Section(
            'Academic Overview',
            new Grid(
                new Columns(3),
                new Stat('Students', CountProvider::class),
                new Stat('Teachers', CountProvider::class),
            ),
            new Tailwind(
                new Slot(Tailwind::Wrapper, [
                    'rounded-2xl',
                    'border',
                    'border-slate-200',
                    'bg-white',
                    'p-6',
                    'shadow-sm',
                ]),
            ),
        ),
    ];

    foreach ($examples as $name => $example) {
        expect(array_map(strval(...), $example->validate()->errors()))->toBe([], $name);
        app(Registry::class)->register(new Layout('example_'.$name, $view, $example));
        expect(layoutHtml('example_'.$name))->toContain('<data value="4">4</data>');
    }

    expect(layoutHtml('example_tailwind'))->toContain('rounded-2xl border border-slate-200 bg-white shadow-sm p-6');
});

it('runs the documented split example in a blueprint', function (): void {
    app(Registry::class)->register(new Blueprint('roster', new Ability('viewAny', Student::class), new Source(Student::class),
        new Split(
            new Main(
                new Table(
                    new Text('name', 'Name'),
                    new Text('level', 'Level'),
                ),
            ),
            new Aside(
                new ActivityFeed('Recent enrolments', new ActivitySource(Student::class, title: 'name', time: 'enrolled_at')),
            ),
        ),
    ));

    $html = squish(Rendering::renderAuthorized('blueprint:roster', RenderMode::Livewire)->toHtml());

    expect(substr_count($html, 'wire:name="gcego-table"'))->toBe(1)
        ->and($html)->toMatch('/<aside[^>]*>.*Recent enrolments.*Chidi.*<\/aside>/');
});
