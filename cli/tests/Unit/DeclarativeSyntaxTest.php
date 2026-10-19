<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Blueprints\Blueprint;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

it('accepts the framework\'s target declarative syntax unchanged, and the positional shorthand', function (): void {
    $explicit = new Blueprint(
        new Property(Blueprint::Name, 'subjects'),
        new Property(Blueprint::Title, 'Subject Management'),
        new Source(new Property(Source::Model, Subject::class)),
        new Table(new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'))),
        new Form(new TextInput(new Property(TextInput::Name, 'name'), new Property(TextInput::Label, 'Subject Name'), new Property(TextInput::Required, true))),
        new Create,
        new Edit,
    );
    $shorthand = new Blueprint('courses', new Source(Subject::class), new Table(new Text('name', 'Subject Name')), new Form(new TextInput('name', 'Subject Name', new Property(TextInput::Required, true))), new Create, new Edit);

    foreach ([$explicit, $shorthand] as $blueprint) {
        expect($blueprint->validate()->errors())->toBe([])
            ->and(app(DefinitionKinds::class)->get('blueprint')?->validate($blueprint))->toBe([]);
        app(RegistryInterface::class)->register($blueprint);
    }

    expect(app(RegistryInterface::class)->has('blueprint:subjects'))->toBeTrue()->and(app(RegistryInterface::class)->has('blueprint:courses'))->toBeTrue();
});
