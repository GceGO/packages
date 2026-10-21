<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures;

use Gcego\Actions\Delete;
use Gcego\Actions\View;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Badge as BadgeEntry;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Page;
use Gcego\Pages\Tests\Fixtures\Models\Document;
use Gcego\Pages\Tests\Fixtures\Models\Note;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Tests\Fixtures\Models\Topic;
use Gcego\Pages\Tests\Fixtures\Providers\SubjectCount;
use Gcego\Pages\Tests\Fixtures\Titles\SubjectTitle;
use Gcego\Pages\Types\CreatePage;
use Gcego\Pages\Types\CustomPage;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Pages\Types\EditPage;
use Gcego\Pages\Types\IndexPage;
use Gcego\Pages\Types\ViewPage;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\TenantMember;
use Gcego\Tables\Columns\Badge;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Widgets\Statistics\Stat;

/**
 * The pages registered in every test (through PagesProvider, so their
 * routes are registered when the application boots).
 */
final class Definitions
{
    /**
     * @return list<Page>
     */
    public static function all(): array
    {
        return [
            self::dashboard(),
            self::subjects(),
            self::createSubject(),
            self::editSubject(),
            self::viewSubject(),
            self::about(),
            self::reports(),
            self::members(),
            self::catalogue(),
            self::topics(),
            self::archive(),
        ];
    }

    public static function dashboard(): DashboardPage
    {
        return new DashboardPage(
            new Property(DashboardPage::Name, 'dashboard'),
            new Property(DashboardPage::Title, 'Dashboard'),
            new Property(DashboardPage::Path, ''),
            new Property(DashboardPage::Icon, 'home'),
            new Property(DashboardPage::Navigation, true),
            new Property(DashboardPage::NavigationOrder, -10),
            new Authenticated,
            new Grid(
                new Columns(3),
                new Stat('Subjects', SubjectCount::class, new Authenticated),
                new Stat('Reports', 12, new Permission('reports.view')),
            ),
        );
    }

    public static function subjects(): IndexPage
    {
        return new IndexPage(
            new Property(IndexPage::Name, 'subjects'),
            new Property(IndexPage::Title, 'Subjects'),
            new Property(IndexPage::Description, 'Every subject of your institution.'),
            new Property(IndexPage::Icon, 'academic-cap'),
            new Property(IndexPage::Navigation, true),
            new Property(IndexPage::NavigationGroup, 'Academic'),
            new Permission('subjects.view'),
            new Source(Subject::class),
            new PageLink('New subject', 'subjects.create', new Property(PageLink::Intent, 'primary')),
            new Table(
                new Text('name', 'Name'),
                new Text('code', 'Code'),
                new Badge('status', 'Status'),
            ),
            new View,
        );
    }

    public static function createSubject(): CreatePage
    {
        return new CreatePage(
            new Property(CreatePage::Name, 'subjects.create'),
            new Property(CreatePage::Title, 'Create subject'),
            new Permission('subjects.create'),
            new Source(Subject::class),
            new Form(
                new TextInput('name', 'Name', new Property(TextInput::Required, true)),
                new TextInput('code', 'Code', new Property(TextInput::Required, true)),
            ),
        );
    }

    public static function editSubject(): EditPage
    {
        return new EditPage(
            new Property(EditPage::Name, 'subjects.edit'),
            new Property(EditPage::Title, 'Edit :record'),
            new Permission('subjects.update'),
            new Source(Subject::class),
            new Delete,
            new Form(
                new TextInput('name', 'Name', new Property(TextInput::Required, true)),
                new TextInput('code', 'Code', new Property(TextInput::Required, true)),
            ),
        );
    }

    public static function viewSubject(): ViewPage
    {
        return new ViewPage(
            new Property(ViewPage::Name, 'subjects.view'),
            new Property(ViewPage::Title, ':record'),
            new Property(ViewPage::Description, 'Subject :record'),
            new Permission('subjects.view'),
            new Source(Subject::class),
            new PageLink('Edit', 'subjects.edit'),
            new Delete,
            new Infolist(
                new TextEntry('name', 'Name'),
                new TextEntry('code', 'Code'),
                new BadgeEntry('status', 'Status'),
            ),
        );
    }

    public static function about(): Page
    {
        return new Page(
            'about',
            'About GCEGO',
            new Authenticated,
            new Section('About this platform', new Content("GCEGO helps candidates prepare for the GCE.\n\nIt is <b>free</b> & open.")),
        );
    }

    public static function reports(): CustomPage
    {
        return new CustomPage(
            new Property(CustomPage::Name, 'reports.overview'),
            new Property(CustomPage::Title, 'Reports'),
            new Permission('reports.view'),
            new Tabs(
                new Tab('Attendance', new Content('Attendance is above 90%.')),
                new Tab('Fees', new Content('Fees are due on Friday.')),
            ),
            new Section('Finance', new Permission('reports.finance'), new Content('Confidential finance summary.')),
        );
    }

    public static function members(): CustomPage
    {
        return new CustomPage(
            new Property(CustomPage::Name, 'institutions.members'),
            new Property(CustomPage::Title, 'Members'),
            new Property(CustomPage::Path, 'institutions/{institution}/members'),
            new Property(CustomPage::Where, ['institution' => '[0-9]+']),
            new Property(CustomPage::Middleware, ['gcego.tenant:required']),
            new Property(CustomPage::Navigation, true),
            new Property(CustomPage::NavigationGroup, 'Academic'),
            new TenantMember,
            new Content('Members of this institution.'),
        );
    }

    public static function catalogue(): ViewPage
    {
        return new ViewPage(
            new Property(ViewPage::Name, 'catalogue.entry'),
            new Property(ViewPage::Path, 'catalogue/{record}'),
            new Property(ViewPage::RecordKey, 'uuid'),
            new Property(ViewPage::TitleProvider, SubjectTitle::class),
            new Permission('subjects.view'),
            new Source(Document::class),
            new Infolist(new TextEntry('name', 'Name')),
        );
    }

    public static function topics(): ViewPage
    {
        return new ViewPage(
            new Property(ViewPage::Name, 'topics.view'),
            new Property(ViewPage::RecordKey, 'slug'),
            new Property(ViewPage::RecordTitle, 'code'),
            new Permission('subjects.view'),
            new Source(Topic::class),
            new Infolist(new TextEntry('name', 'Name')),
        );
    }

    public static function archive(): ViewPage
    {
        return new ViewPage(
            new Property(ViewPage::Name, 'archive.view'),
            new Property(ViewPage::RecordKey, 'ulid'),
            new Property(ViewPage::Breadcrumbs, false),
            new Permission('subjects.view'),
            new Source(Note::class),
            new Infolist(new TextEntry('name', 'Name')),
        );
    }
}
