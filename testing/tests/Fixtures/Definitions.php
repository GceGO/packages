<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures;

use Gcego\Actions\Action;
use Gcego\Actions\BulkDelete;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Data\Search\Search;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Configuration\Access as EntryAccess;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Layout;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Access as ColumnAccess;
use Gcego\Tables\Table;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Data\Value;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

/**
 * The definitions the toolkit's own tests run against, built with the
 * public APIs of each package.
 */
final class Definitions
{
    /**
     * @return list<object>
     */
    public static function all(): array
    {
        return [self::subjects(), self::reports(), self::admin(), self::students(), self::enrolments(), self::dashboard(), self::review()];
    }

    public static function subjects(): Blueprint
    {
        $secret = Permission::inTenant('subjects.secrets');

        return new Blueprint(
            'subjects',
            new Ability('viewAny', Subject::class),
            new Source(Subject::class, new Search('name', 'code'), new Sort('name')),
            new Table(
                new Text('name', 'Subject Name', new Property(Text::Searchable, true), new Property(Text::Sortable, true)),
                new Text('code', 'Code', new Property(Text::Sortable, true)),
                new Text('secret_note', 'Secret note', new ColumnAccess($secret)),
            ),
            new Form(
                new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
                new TextInput('code', 'Code'),
            ),
            new Infolist(
                new TextEntry('name', 'Subject Name'),
                new TextEntry('code', 'Code'),
                new TextEntry('secret_note', 'Secret note', new EntryAccess($secret, mask: true)),
            ),
            new Create,
            new Edit,
            new Delete,
            new Action('publish', 'Publish', new Property(Action::Ability, 'publish'), new Property(Action::Record, true), new Property(Action::Placement, ['row', 'record']), new Handler(static fn (ActionContext $context): mixed => $context->model()->update(['published' => true]))),
            new Action('rename', 'Rename', new Property(Action::Ability, 'update'), new Property(Action::Record, true), new Input(['name' => ['required', 'string', 'max:40']]), new Handler(static fn (ActionContext $context): mixed => $context->model()->update(['name' => $context->input->get('name')]))),
            new BulkDelete,
        );
    }

    public static function reports(): DashboardPage
    {
        return new DashboardPage(new Property(DashboardPage::Name, 'reports'), new Property(DashboardPage::Title, 'Reports'), new Ability('view-reports'), new Section('Summary', new Content('Monthly reports.')));
    }

    public static function admin(): Navigation
    {
        return new Navigation('admin',
            new Item(new Property(Item::Name, 'home'), new Property(Item::Label, 'Home'), new Property(Item::Route, 'home')),
            new Item(new Property(Item::Name, 'reports'), new Property(Item::Label, 'Reports'), new Property(Item::Route, 'reports'), new Ability('view-reports')),
        );
    }

    public static function students(): Stat
    {
        return new Stat(new Property(Stat::Name, 'students'), new Property(Stat::Label, 'Students'), new Value(static fn (): int => 42), new Authenticated);
    }

    public static function enrolments(): Stat
    {
        return new Stat(new Property(Stat::Name, 'enrolments'), new Property(Stat::Label, 'Enrolments'), new Value(static fn (): int => 7), new DateRange, new Authenticated);
    }

    public static function dashboard(): Layout
    {
        return new Layout('dashboard', new Authenticated,
            new Grid(new Section('Summary', new Content('Totals.')), new Section('Activity', new Content('Latest changes.'))),
            new Tabs(new Tab('Overview', new Property(Tab::Name, 'overview')), new Tab('Details', new Property(Tab::Name, 'details'))),
        );
    }

    public static function review(): Workflow
    {
        return new Workflow(
            'review',
            'Subject review',
            new State('draft', 'Draft'),
            new State('submitted', 'Submitted'),
            new State('approved', 'Approved', terminal: true),
            new Transition('submit', 'draft', 'submitted'),
            new Transition('approve', 'submitted', 'approved', ability: 'publish'),
            subject: Subject::class,
        );
    }
}
