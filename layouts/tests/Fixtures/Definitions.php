<?php

declare(strict_types=1);

namespace Gcego\Layouts\Tests\Fixtures;

use Gcego\Actions\Create;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as InfolistText;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Actions;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Card;
use Gcego\Layouts\Components\EmptyState;
use Gcego\Layouts\Components\Footer;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Layout;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text as TableText;
use Gcego\Tables\Table;
use Gcego\Widgets\Charts\LineChart;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Statistics\Stat;

final class Definitions
{
    /**
     * A registered dashboard: widgets in sections, grids and tabs, with
     * requirements on a stat, a section and a tab.
     */
    public static function dashboard(): Layout
    {
        return new Layout('dashboard', new Property(Layout::Label, 'Dashboard'), new Ability('viewAny', Student::class),
            new Section(new Property(Section::Name, 'overview'), new Property(Section::Label, 'Overview'), new Property(Section::Description, 'This term so far'),
                new Grid(new Property(Grid::Name, 'stats'), new Columns(3),
                    new Stat(new Property(Stat::Name, 'students'), new Property(Stat::Label, 'Students'), new Aggregate(Student::class)),
                    new Stat(new Property(Stat::Name, 'girls'), new Property(Stat::Label, 'Girls'), new Aggregate(Student::class, filters: [new Filter('gender', 'f')])),
                    new Stat(new Property(Stat::Name, 'boys'), new Property(Stat::Label, 'Boys'), new Aggregate(Student::class, filters: [new Filter('gender', 'm')]), new Permission('dashboard.boys'), new ColumnSpan(['default' => 'full', 'lg' => 1])),
                ),
            ),
            new Section(new Property(Section::Name, 'finance'), new Property(Section::Label, 'Finance'), new Permission('dashboard.finance'),
                new Grid(new Stat(new Property(Stat::Name, 'fees'), new Property(Stat::Label, 'Fees'), new Property(Stat::Value, 1200))),
            ),
            new Section(new Property(Section::Name, 'trends'), new Property(Section::Label, 'Enrolment trends'),
                new LineChart(new Property(LineChart::Name, 'enrolment'), new Property(LineChart::Label, 'Enrolment'), new Aggregate(Student::class, date: 'enrolled_at', bucket: 'month'), new DateRange(default: 'this_year')),
            ),
            new Tabs(new Property(Tabs::Name, 'more'), new Property(Tabs::Label, 'More'),
                new Tab('Summary', new Stat(new Property(Stat::Name, 'levels'), new Property(Stat::Label, 'Levels'), new Property(Stat::Value, 3))),
                new Tab('Secret', new Permission('dashboard.secret'), new Stat(new Property(Stat::Name, 'secret'), new Property(Stat::Label, 'Secret'), new Property(Stat::Value, 7))),
                new Tab('Notes', new EmptyState('No notes yet', 'Notes appear here.')),
            ),
        );
    }

    /**
     * A blueprint whose parts live in layouts: a table and an action in a
     * split, an infolist and a form in tabs, a widget in the aside.
     */
    public static function students(): Blueprint
    {
        return new Blueprint('students', new Ability('viewAny', Student::class), new Source(Student::class),
            new Split(new Property(Split::Name, 'list'),
                new Main(new Property(Main::Name, 'main'), new Section(new Property(Section::Name, 'students'), new Property(Section::Label, 'Students'), new Actions(new Create), new Table(new TableText('name', 'Name'), new TableText('level', 'Level')))),
                new Aside(new Property(Aside::Name, 'summary'), new Property(Aside::Label, 'Summary'), new Stat(new Property(Stat::Name, 'count'), new Property(Stat::Label, 'Enrolled'), new Aggregate(Student::class))),
            ),
            new Tabs(new Property(Tabs::Name, 'record'),
                new Tab('Profile', new Infolist(new InfolistText('name', 'Name'), new InfolistText('level', 'Level'))),
                new Tab('Edit', new Card(new Property(Card::Name, 'editor'), new Property(Card::Label, 'Edit student'), new Form(new TextInput('name', 'Name')), new Footer(new EmptyState('Saved changes appear in the history.')))),
            ),
        );
    }
}
