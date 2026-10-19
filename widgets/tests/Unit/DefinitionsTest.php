<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Exceptions\ComponentException;
use Gcego\Components\Property;
use Gcego\Data\Support\Callback;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Widgets\Activity\ActivitySource;
use Gcego\Widgets\Charts\Chart;
use Gcego\Widgets\Configuration\Cache;
use Gcego\Widgets\Configuration\Refresh;
use Gcego\Widgets\Configuration\Trend;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DataProvider;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Data\Value;
use Gcego\Widgets\Exceptions\InvalidWidgetException;
use Gcego\Widgets\Progress\Progress;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Widgets\Statistics\Stats;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Tests\Fixtures\Providers\EnrolmentProvider;
use Gcego\Widgets\Tests\Fixtures\Providers\StudentCountProvider;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

it('accepts the documented definitions', function (Widget $widget): void {
    expect($widget->validate()->isValid())->toBeTrue();
})->with([
    'deferred closure' => [new Stat('Total Students', fn () => 1240)],
    'provider shorthand' => [new Stat('Total Students', StudentCountProvider::class)],
    'property syntax' => [new Stat(new Property(Stat::Name, 'total_students'), new Property(Stat::Label, 'Total Students'), new Value(fn () => 1240))],
    'stats' => [new Stats(new Stat('Students', StudentCountProvider::class), new Stat('Teachers', fn () => 85), new Stat('Subjects', new Aggregate(Student::class)))],
    'chart' => [new Chart(new Property(Chart::Name, 'student_enrolment'), new Property(Chart::Label, 'Student Enrolment'), new Property(Chart::Type, 'line'), new DataProvider(EnrolmentProvider::class))],
    'progress' => [new Progress(new Property(Progress::Name, 'syllabus_completion'), new Property(Progress::Label, 'Syllabus Completion'), new Property(Progress::Value, 72), new Property(Progress::Maximum, 100))],
    'tailwind' => [new Stat(new Property(Stat::Name, 'total_students'), new Property(Stat::Label, 'Total Students'), new Value(fn () => 1), new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'p-6', 'shadow-sm']), new Slot(Stat::ValueSlot, ['text-3xl', 'font-bold'])))],
]);

it('derives stable names and keeps definitions lazy and immutable', function (): void {
    $calls = 0;
    $queries = queriesDuring(function () use (&$calls, &$stat): void {
        $stat = new Stat('Total Students!', function () use (&$calls) {
            $calls++;

            return 1;
        });
        $stat->validate();
        $stat->normalize();
    });

    expect($stat->widgetName())->toBe('total_students')
        ->and($stat->title())->toBe('Total Students!')
        ->and($stat->dataSource())->toBeInstanceOf(Value::class)
        ->and($calls)->toBe(0)
        ->and($queries)->toBe([])
        ->and((string) json_encode($stat->normalize()))->not->toContain('Closure')
        ->and($stat->withProperty(new Property(Stat::Format, 'compact'))->get(Stat::Format))->toBe('compact')
        ->and($stat->get(Stat::Format))->toBe('number')
        ->and((new Stat('Students', StudentCountProvider::class))->dataSource())->toBeInstanceOf(DataProvider::class)
        ->and((new Stat('Students', 42))->dataSource())->toBeInstanceOf(Value::class);
});

it('rejects invalid definitions', function (Closure $build): void {
    $widget = null;

    try {
        $widget = $build();
    } catch (InvalidWidgetException|ComponentException $exception) {
        expect(true)->toBeTrue();

        return;
    }

    expect($widget->validate()->isValid())->toBeFalse();
})->with([
    'no name or label' => [fn () => new Stat(new Value(1))],
    'bad name' => [fn () => new Stat(new Property(Stat::Name, 'Bad Name'), new Value(1))],
    'two data sources' => [fn () => new Stat('X', new Value(1), new Aggregate(Student::class))],
    'duplicate stats' => [fn () => new Stats(new Stat('Students', fn () => 1), new Stat('Students', fn () => 2))],
    'unknown chart type' => [fn () => new Chart('X', new Property(Chart::Type, 'radar'))],
    'unknown format' => [fn () => new Stat('X', new Property(Stat::Format, 'hex'))],
    'unsafe url' => [fn () => new Stat('X', new Property(Stat::Url, 'javascript:alert(1)'))],
    'forged provider' => [fn () => new DataProvider(stdClass::class)],
    'aggregate on a non-model' => [fn () => new Aggregate('App\\Nope')],
    'aggregate injection' => [fn () => new Aggregate(Student::class, 'sum', 'amount) --')],
    'compare without a date' => [fn () => new Aggregate(Student::class, compare: true)],
    'polling too fast' => [fn () => new Refresh(every: 1)],
    'bad refresh event' => [fn () => new Refresh(on: ['alert(1)'])],
    'cache scope' => [fn () => new Cache(scope: 'global')],
    'trend meaning' => [fn () => new Trend(increase: 'great')],
    'date range preset' => [fn () => new DateRange(default: 'last_century')],
    'closure property' => [fn () => new Property(Stat::Value, fn () => 1)],
]);

it('exposes layout metadata and interaction needs', function (): void {
    expect((new Stat('A', 1, new Property(Stat::Span, 2), new Property(Stat::Height, 'sm')))->layout())->toBe(['span' => 2, 'height' => 'sm', 'min_width' => 'xs'])
        ->and((new Stat('A', 1))->needsLivewire())->toBeFalse()
        ->and((new Stat('A', 1, new DateRange))->needsLivewire())->toBeTrue()
        ->and((new Stat('A', 1, new Refresh(every: 60)))->needsLivewire())->toBeTrue()
        ->and((new Stats(new Stat('A', 1, new Refresh(on: ['x']))))->needsLivewire())->toBeTrue()
        ->and(($callback = new Stat('A', new Property(Stat::Value, new Callback(fn () => 7))))->validate()->isValid())->toBeTrue()
        ->and($callback->dataSource())->toBeInstanceOf(Value::class);
});

it('validates configuration', function (): void {
    expect(WidgetsConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-widgets.php'))->toEqual(new WidgetsConfig)
        ->and(fn () => WidgetsConfig::fromArray(['refresh' => ['minimum_interval_seconds' => 1]]))->toThrow(InvalidWidgetException::class)
        ->and(fn () => WidgetsConfig::fromArray(['formatting' => ['currency' => 'franc']]))->toThrow(InvalidWidgetException::class)
        ->and(fn () => WidgetsConfig::fromArray(['activity' => ['default_limit' => 80]]))->toThrow(InvalidWidgetException::class);
});

it('takes a typed Luminaries icon for an activity source and refuses other icon values', function (): void {
    expect((new ActivitySource(Student::class, title: 'name', time: 'created_at', icon: Heroicon::UserPlus))->icon)->toBe('heroicon:user-plus')
        ->and(fn () => new ActivitySource(Student::class, title: 'name', time: 'created_at', icon: '"><svg>'))->toThrow(InvalidWidgetException::class, 'icon must be an icon name.');
});
