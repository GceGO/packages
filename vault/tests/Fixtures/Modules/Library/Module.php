<?php

declare(strict_types=1);

use Gcego\Actions\BulkDelete;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Access as FieldAccess;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Security\Permissions\Permission;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Access as ColumnAccess;
use Gcego\Tables\Table;
use Gcego\Vault\Tests\Fixtures\Models\Book;

return new Module(
    new Property(Module::Name, 'library'),
    new Property(Module::Title, 'Library'),
    new Permission('modules.library.access'),

    new Resource(
        new Property(Resource::Name, 'books'),
        new Source(Book::class),
        new Property(Resource::RecordTitle, 'title'),
        new Table(
            new Text('title', 'Title'),
            new Text('isbn', 'ISBN', new ColumnAccess(read: new Permission('books.isbn.view'))),
            new BulkDelete,
        ),
        new Form(
            new TextInput('title', 'Title', new Property(TextInput::Required, true)),
            new TextInput('isbn', 'ISBN', new FieldAccess(read: new Permission('books.isbn.view'), write: new Permission('books.isbn.update'))),
        ),
        new Infolist(new TextEntry('title', 'Title')),
        new Create,
        new Edit,
        new Delete,
    ),
);
