<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Section;
use Gcego\Modules\Module;
use Gcego\Modules\Relation;
use Gcego\Modules\Relationships\Attach;
use Gcego\Modules\Relationships\Detach;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Tag;
use Gcego\Modules\Tests\Fixtures\Models\Topic;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academic Management'),
    new Property(Module::Navigation, true),
    new Property(Module::NavigationOrder, 10),

    new Resource(
        new Source(Subject::class),
        new Property(Resource::RecordTitle, 'name'),
        new Property(Resource::NavigationBadge, true),
        new Property(Resource::NavigationBadgeVariant, 'success'),
        new Property(Resource::RecordKey, 'id'),

        new Table(
            new Text('name', 'Subject Name'),
            new Text('code', 'Code'),
        ),

        new Form(
            new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
            new TextInput('code', 'Subject Code', new Property(TextInput::Required, true)),
        ),

        new Infolist(
            new TextEntry('name', 'Subject Name'),
            new TextEntry('code', 'Subject Code'),
        ),

        new Create,
        new Edit,
        new Delete,

        new Relation('topics', new Property(Relation::Resource, 'topics')),
        new Relation('tags', new Property(Relation::Resource, 'tags'), new Attach, new Detach),
    ),

    new Resource(
        new Source(Topic::class),
        new Property(Resource::RecordKey, 'id'),
        new Table(new Text('name', 'Topic')),
        new Form(new TextInput('name', 'Topic', new Property(TextInput::Required, true))),
        new Infolist(new TextEntry('name', 'Topic')),
        new Create,
        new Edit,
        new Delete,
    ),

    new Resource(
        new Source(Tag::class),
        new Property(Resource::Navigation, false),
        new Table(new Text('name', 'Tag')),
    ),

    new DashboardPage(
        new Property(DashboardPage::Name, 'overview'),
        new Property(DashboardPage::Title, 'Academic Overview'),
        new Authenticated,

        new Section('Summary', new Content('Subjects, topics and tags of your institution.')),
    ),
);
