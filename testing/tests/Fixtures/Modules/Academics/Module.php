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
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academics'),
    new Property(Module::Slug, 'academics'),

    new Resource(
        new Source(Subject::class),
        new Property(Resource::Name, 'courses'),
        new Table(new Text('name', 'Name')),
        new Form(new TextInput('name', 'Name', new Property(TextInput::Required, true))),
        new Infolist(new TextEntry('name', 'Name')),
        new Create,
        new Edit,
        new Delete,
    ),
);
