<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Media\Columns\MediaFile;
use Gcego\Media\Columns\MediaImage;
use Gcego\Media\Entries\MediaPreview;
use Gcego\Media\Fields\MediaUpload;
use Gcego\Media\Tests\Fixtures\Models\Subject;
use Gcego\Media\Tests\Fixtures\Models\Topic;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academic Management'),
    new Property(Module::NavigationOrder, 10),

    new Resource(
        new Source(Subject::class),
        new Property(Resource::RecordTitle, 'name'),
        new Property(Resource::RecordKey, 'id'),
        new Table(new Text('name', 'Subject Name'), new Text('code', 'Code'), new MediaImage('cover', 'Cover'), new MediaFile('attachments', 'Files')),
        new Form(
            new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
            new TextInput('code', 'Subject Code', new Property(TextInput::Required, true)),
            (new MediaUpload('cover', 'Cover'))->image(),
            (new MediaUpload('attachments', 'Attachments'))->multiple()->maxFiles(3),
        ),
        new Infolist(new TextEntry('name', 'Subject Name'), new TextEntry('code', 'Subject Code'), new MediaPreview('cover', 'Cover'), new MediaPreview('attachments', 'Attachments')),
        new Create,
        new Edit,
    ),

    new Resource(
        new Source(Topic::class),
        new Property(Resource::RecordKey, 'id'),
        new Table(new Text('name', 'Topic')),
    ),
);
