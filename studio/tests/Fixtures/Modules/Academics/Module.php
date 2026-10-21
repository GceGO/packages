<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Studio\Tests\Fixtures\Models\Subject;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academic Management'),

    new Resource(
        new Source(Subject::class),
        new Property(Resource::RecordTitle, 'name'),
        new Property(Resource::RecordKey, 'id'),
        new Table(new Text('name', 'Subject Name'), new Text('code', 'Code')),
        new Form(new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)), new TextInput('code', 'Subject Code', new Property(TextInput::Required, true))),
        new Infolist(new TextEntry('name', 'Subject Name'), new TextEntry('code', 'Subject Code')),
        new Create,
        new Edit,
    ),
);
