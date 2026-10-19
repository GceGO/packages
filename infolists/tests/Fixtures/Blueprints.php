<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests\Fixtures;

use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Actions\Restore;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Configuration\Access;
use Gcego\Infolists\Configuration\Resolve;
use Gcego\Infolists\Configuration\VisibleWhen;
use Gcego\Infolists\Entries\Badge;
use Gcego\Infolists\Entries\Boolean;
use Gcego\Infolists\Entries\Date;
use Gcego\Infolists\Entries\DateTime;
use Gcego\Infolists\Entries\Icon;
use Gcego\Infolists\Entries\Image;
use Gcego\Infolists\Entries\KeyValue;
use Gcego\Infolists\Entries\Link;
use Gcego\Infolists\Entries\ListEntry;
use Gcego\Infolists\Entries\Money;
use Gcego\Infolists\Entries\Number;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Layout\Grid;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Layout\Split;
use Gcego\Infolists\Layout\Tab;
use Gcego\Infolists\Layout\Tabs;
use Gcego\Infolists\Tests\Fixtures\Models\Course;
use Gcego\Infolists\Tests\Fixtures\Models\Document;
use Gcego\Infolists\Tests\Fixtures\Models\Report;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Illuminate\Database\Eloquent\Model;

final class Blueprints
{
    public static function infolist(): Infolist
    {
        return new Infolist(
            new Split(
                new Tabs(
                    new Tab('Overview',
                        new Section('Subject Information',
                            new Grid(2,
                                new Text('name', 'Subject Name', new Property(Text::Copyable, true)),
                                new Text('code', 'Subject Code'),
                                new Text('category.name', 'Category'),
                                new Badge('status', 'Status', new Property(Badge::Variants, ['published' => 'success', 'draft' => 'warning'])),
                            ),
                            new Text('description', 'Description', new Property(Text::Markdown, true), new Property(Text::ColumnSpan, 'full')),
                            new Text('notes', 'Notes', new Property(Text::Multiline, true), new Property(Text::HiddenWhenEmpty, true)),
                        ),
                        new Section('Pricing',
                            new Number('credits', 'Credits', new Property(Number::Decimals, 1)),
                            new Money('fee', 'Fee'),
                            new Boolean('is_published', 'Published'),
                            new Icon('priority', 'Priority', new Property(Icon::Icons, ['high' => 'exclamation-triangle']), new Property(Icon::Labels, ['high' => 'High priority'])),
                        ),
                    ),
                    new Tab('Curriculum',
                        new RepeatableEntry('topics', 'Topics', new Text('title', 'Topic'), new Text('teacher.name', 'Teacher')),
                        new RepeatableEntry('objectives', 'Objectives', new Text('title', 'Objective'), new Text('description', 'Description')),
                        new ListEntry('tags.label', 'Tags', new Property(ListEntry::Style, 'badges')),
                        new ListEntry('languages', 'Languages'),
                        new KeyValue('settings', 'Settings', new Property(KeyValue::Keys, ['term' => 'Term', 'grading.scale' => 'Grading scale'])),
                    ),
                ),
                new Section('Record',
                    new Link('website', 'Website', new Property(Link::NewTab, true)),
                    new Link('contact', 'Contact', new Property(Link::Mailto, true)),
                    new Image('logo', 'Logo'),
                    new Date('starts_on', 'Starts'),
                    new DateTime('published_at', 'Published At'),
                    new Text('secret_notes', 'Secret notes', new Access(read: new Permission('subjects.secrets'))),
                    new Text('salary_band', 'Band', new Access(read: new Permission('subjects.secrets'), mask: true)),
                    new Text('archive_reason', 'Archive reason', new VisibleWhen('status', 'archived')),
                    new Number('topic_count', 'Topics', new Resolve(fn (Subject $subject) => $subject->topics->count())),
                    new Property(Section::Collapsible, true),
                ),
            ),
            new Section('Restricted', new Permission('subjects.secrets'), new Text('internal_code', 'Internal code')),
            new Edit,
            new Delete,
            new Restore,
        );
    }

    public static function subjects(): Blueprint
    {
        return new Blueprint('subjects', new Ability('viewAny', Subject::class), new Source(Subject::class), new Form(new TextInput('name', 'Subject Name', new Property(TextInput::Required, true))), self::infolist());
    }

    public static function minimal(): Blueprint
    {
        return new Blueprint('minimal', new Ability('viewAny', Subject::class), new Source(Subject::class), new Infolist(
            new Text('name', 'Subject Name'),
            new Text('code', 'Subject Code'),
            new Badge('status', 'Status'),
            new DateTime('created_at', 'Created At'),
        ));
    }

    public static function archive(): Blueprint
    {
        return new Blueprint('archive', new Ability('viewAny', Subject::class), new Source(Subject::class), new Infolist(new Text('name', 'Subject Name'), new Property(Infolist::WithTrashed, true)));
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function keyed(string $name, string $model): Blueprint
    {
        return new Blueprint($name, new Ability('viewAny', $model), new Source($model), new Infolist(new Text('title', 'Title')));
    }

    /**
     * @return list<Blueprint>
     */
    public static function all(): array
    {
        return [self::subjects(), self::minimal(), self::archive(), self::keyed('documents', Document::class), self::keyed('reports', Report::class), self::keyed('courses', Course::class)];
    }
}
