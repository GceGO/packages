<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures;

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Properties\Property;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Source;
use Gcego\Infolists\Entries\Text as InfolistText;
use Gcego\Infolists\Infolist;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text as TableText;
use Gcego\Tables\Table;
use Gcego\Widgets\Activity\ActivityFeed;
use Gcego\Widgets\Activity\ActivitySource;
use Gcego\Widgets\Charts\BarChart;
use Gcego\Widgets\Charts\Chart;
use Gcego\Widgets\Charts\DoughnutChart;
use Gcego\Widgets\Charts\LineChart;
use Gcego\Widgets\Charts\PieChart;
use Gcego\Widgets\Configuration\Cache;
use Gcego\Widgets\Configuration\Refresh;
use Gcego\Widgets\Configuration\Trend;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DataProvider;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Progress\Goal;
use Gcego\Widgets\Progress\Progress;
use Gcego\Widgets\Records\RecentRecords;
use Gcego\Widgets\Records\RecordSummary;
use Gcego\Widgets\Records\TableWidget;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Widgets\Statistics\Stats;
use Gcego\Widgets\Tests\Fixtures\Models\ActivityLog;
use Gcego\Widgets\Tests\Fixtures\Models\Payment;
use Gcego\Widgets\Tests\Fixtures\Models\Result;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Tests\Fixtures\Providers\BadChartProvider;
use Gcego\Widgets\Tests\Fixtures\Providers\EnrolmentProvider;
use Gcego\Widgets\Tests\Fixtures\Providers\FailingProvider;
use Gcego\Widgets\Tests\Fixtures\Providers\SharedStatsProvider;
use Gcego\Widgets\Tests\Fixtures\Providers\StudentCountProvider;
use Gcego\Widgets\Widget;

final class Widgets
{
    public static function students(): Blueprint
    {
        return new Blueprint('students', new Ability('viewAny', Student::class), new Source(Student::class),
            new Infolist(new InfolistText('name', 'Name'), new InfolistText('level', 'Level')),
            new Table(new TableText('name', 'Name'), new TableText('level', 'Level')),
        );
    }

    /**
     * @return list<Widget>
     */
    public static function all(): array
    {
        $view = new Ability('viewAny', Student::class);

        return [
            new Stat('Total Students', StudentCountProvider::class, $view),
            new Stats(
                new Property(Stats::Name, 'overview'),
                new Property(Stats::Label, 'School overview'),
                $view,
                new DateRange(default: 'this_year'),
                new DataProvider(SharedStatsProvider::class),
                new Stat('Students', new Aggregate(Student::class), new Property(Stat::Icon, 'star')),
                new Stat('New students', new Aggregate(Student::class, date: 'enrolled_at', compare: true), new Trend),
                new Stat('Fees collected', new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at'), new Property(Stat::Format, 'currency'), new Permission('dashboard.finance')),
                new Stat('Failed results', new Aggregate(Result::class, filters: [new Filter('passed', false)], date: 'graded_at', compare: true), new Trend(increase: 'negative')),
                new Stat('Rooms'),
                new Stat('Buses', new Trend),
            ),
            new LineChart('Enrolment', new Aggregate(Student::class, date: 'enrolled_at', bucket: 'month', label: 'Students'), new DateRange(default: 'this_year'), $view),
            new BarChart('Students by level', new DataProvider(EnrolmentProvider::class), $view, new Property(Chart::Stacked, true)),
            new PieChart('Level share', new Aggregate(Student::class, groupBy: 'level'), $view),
            new DoughnutChart('Revenue share', new Aggregate(Payment::class, 'sum', 'amount', groupBy: 'method'), $view, new Property(Chart::Format, 'currency')),
            new Chart(new Property(Chart::Name, 'broken_chart'), new Property(Chart::Type, 'line'), new DataProvider(BadChartProvider::class), $view),
            new Progress(new Property(Progress::Name, 'syllabus_completion'), new Property(Progress::Label, 'Syllabus Completion'), new Property(Progress::Value, 72), new Property(Progress::Maximum, 100), $view),
            new Goal('Enrolment target', new Aggregate(Student::class), new Property(Goal::Target, 10), new Property(Goal::Deadline, '2099-12-31'), $view),
            new Goal('Absence limit', new Aggregate(Student::class), new Property(Goal::Target, 2), new Property(Goal::Direction, 'at_most'), $view),
            new ActivityFeed('Recent activity', new ActivitySource(ActivityLog::class, title: 'description', time: 'created_at', actor: 'causer.name'), new Property(ActivityFeed::Limit, 2), new Property(ActivityFeed::Pages, true), $view),
            new RecentRecords('New students', new Property(RecentRecords::Blueprint, 'students'), new Property(RecentRecords::TimeColumn, 'enrolled_at'), new Property(RecentRecords::DescriptionColumn, 'level'), $view),
            new RecordSummary('Selected student', new Property(RecordSummary::Blueprint, 'students'), $view),
            new TableWidget('Student list', new Property(TableWidget::Blueprint, 'students'), $view),
            new Stat('Cached students', StudentCountProvider::class, new Cache(ttl: 600), $view),
            new Stat('Failing', new DataProvider(FailingProvider::class), $view),
            new Stat('Live students', StudentCountProvider::class, new Refresh(every: 10, on: ['students-changed']), $view),
            new Stat('Finance only', new Aggregate(Payment::class, 'sum', 'amount'), new Permission('dashboard.finance')),
        ];
    }
}
