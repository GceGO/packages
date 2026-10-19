<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Forms\Configuration\Condition;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Rules;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\Fields\Checkbox;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\DateTimePicker;
use Gcego\Forms\Fields\EmailInput;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Fields\NumberInput;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Fields\RichEditor;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\Textarea;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\TimePicker;
use Gcego\Forms\Fields\Toggle;
use Gcego\Forms\Form;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\Layout\Grid;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Layout\Step;
use Gcego\Forms\Layout\Tab;
use Gcego\Forms\Layout\Tabs;
use Gcego\Forms\Layout\Wizard;
use Gcego\Forms\Schema\SchemaCompiler;
use Gcego\Forms\State\FormState;
use Gcego\Forms\State\StatePath;
use Gcego\Forms\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;

it('accepts shorthand and Property syntax for the same field', function (): void {
    $short = new TextInput('name', 'Subject Name');
    $long = new TextInput(new Property(TextInput::Name, 'name'), new Property(TextInput::Label, 'Subject Name'), new Property(TextInput::Required, true), new Property(TextInput::MaxLength, 255));

    expect($short->fieldName())->toBe('name')
        ->and($short->label())->toBe('Subject Name')
        ->and($long->fieldName())->toBe('name')
        ->and($long->isRequired())->toBeTrue()
        ->and($long->typeRules())->toBe(['string', 'max:255'])
        ->and((new TextInput('first_name'))->label())->toBe('First Name')
        ->and((new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Subject Code')))->validate()->isValid())->toBeTrue();
});

it('translates field properties into Laravel rules', function (Field $field, array $rules): void {
    expect($field->typeRules())->toEqual($rules);
})->with([
    'text pattern' => [new TextInput('code', new Property(TextInput::MinLength, 3), new Property(TextInput::Pattern, '[A-Z]+')), ['string', 'min:3', 'regex:/^(?:[A-Z]+)$/u']],
    'email' => [new EmailInput('email'), ['string', 'email']],
    'url' => [new TextInput('site', new Property(TextInput::Type, 'url')), ['string', 'url']],
    'textarea' => [new Textarea('body', new Property(Textarea::MaxLength, 10)), ['string', 'max:10']],
    'integer' => [new NumberInput('level', new Property(NumberInput::Integer, true), new Property(NumberInput::Min, 1), new Property(NumberInput::Max, 7)), ['integer', 'min:1', 'max:7']],
    'decimal' => [new NumberInput('credits'), ['numeric']],
    'checkbox' => [new Checkbox('agree'), ['boolean']],
    'required checkbox' => [new Checkbox('agree', new Property(Checkbox::Required, true)), ['accepted']],
    'multiple select' => [new Select('tags', new Property(Select::Multiple, true), new Options(['a' => 'A'])), ['array']],
    'date' => [new DatePicker('on', new Property(DatePicker::Min, '2020-01-01'), new Property(DatePicker::Max, '2030-12-31')), ['date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2030-12-31']],
    'datetime' => [new DateTimePicker('at'), ['date_format:Y-m-d\TH:i']],
    'time' => [new TimePicker('at'), ['date_format:H:i']],
    'repeater' => [new Repeater('items', new TextInput('title'), new Property(Repeater::MinItems, 1), new Property(Repeater::MaxItems, 3)), ['array', 'min:1', 'max:3']],
    'rich' => [new RichEditor('body', new Property(RichEditor::MaxLength, 5000)), ['string', 'max:5000']],
]);

it('rejects invalid field definitions', function (Component $component): void {
    expect($component->validate()->isValid())->toBeFalse();
})->with([
    'bad name' => [new TextInput('bad name')],
    'unknown type' => [new TextInput('x', new Property(TextInput::Type, 'color'))],
    'bad pattern' => [new TextInput('x', new Property(TextInput::Pattern, '[unclosed'))],
    'bad date bound' => [new DatePicker('x', new Property(DatePicker::Min, '01/02/2020'))],
    'unsafe rich element' => [new RichEditor('x', new Property(RichEditor::Allowed, ['script']))],
    'bad upload directory' => [new FileUpload('x', new Property(FileUpload::Directory, '../etc'))],
    'grid columns' => [new Grid(9, new TextInput('x'))],
    'empty repeater' => [new Repeater('items')],
    'tabs with fields' => [new Tabs(new TextInput('x'))],
    'non-form child' => [new Form(new Component)],
]);

it('compiles schemas with stable paths, repeaters, conditions and wizard steps', function (): void {
    $form = new Form(
        new Section('Basic', new Grid(2, new TextInput('name'), new Select('category', new Options(['a' => 'A'])))),
        new TextInput('code', new VisibleWhen('category', 'a')),
        new Repeater('objectives', new TextInput('title'), new Textarea('details', VisibleWhen::filled('title'))),
        new Tabs(new Tab('One', new TextInput('one')), new Tab('Two', new TextInput('two'))),
    );
    $schema = (new SchemaCompiler)->compile($form);

    expect($schema->paths())->toBe(['name', 'category', 'code', 'objectives', 'objectives.*.title', 'objectives.*.details', 'one', 'two'])
        ->and($schema->live)->toBe(['category', 'objectives.*.title'])
        ->and($schema->dependencies)->toBe(['code' => ['category'], 'objectives.*.details' => ['objectives.*.title']])
        ->and($schema->entry('objectives.*.title')?->repeater)->toBe('objectives')
        ->and($schema->stepCount())->toBe(0);

    $wizard = (new SchemaCompiler)->compile(new Form(new Wizard(new Step('First', new TextInput('a')), new Step('Second', new TextInput('b')))));

    expect($wizard->steps)->toBe(['First', 'Second'])
        ->and($wizard->entry('b')?->step)->toBe(1);
});

it('rejects duplicate paths, unknown dependencies and cycles', function (Form $form, string $message): void {
    expect(fn () => (new SchemaCompiler)->compile($form))->toThrow(InvalidFieldException::class, $message)
        ->and($form->validate()->isValid())->toBeFalse();
})->with([
    'duplicate' => [new Form(new TextInput('name'), new Section('S', new TextInput('name'))), 'two fields share this state path'],
    'unknown dependency' => [new Form(new TextInput('a', new VisibleWhen('missing', 1))), 'depends on "missing"'],
    'visible only when filled itself' => [new Form(new TextInput('a', VisibleWhen::filled('a'))), 'could never get'],
    'cycle' => [new Form(new TextInput('a', new VisibleWhen('b', 1)), new TextInput('b', new VisibleWhen('a', 1))), 'cycle (a -> b -> a)'],
    'options dependency' => [new Form(new Select('topic', new Options(fn () => [], dependsOn: ['subject']))), 'depends on "subject"'],
    'two wizards' => [new Form(new Wizard(new Step('A', new TextInput('a'))), new Wizard(new Step('B', new TextInput('b')))), 'one Wizard'],
]);

it('keeps definitions immutable and query-free', function (): void {
    $queries = queriesDuring(function () use (&$form): void {
        $form = subjectsForm();
        $form->validate();
        (new SchemaCompiler)->compile($form);
    });
    $field = new TextInput('name');

    expect($queries)->toBe([])
        ->and($field->withProperty(new Property(TextInput::Required, true))->isRequired())->toBeTrue()
        ->and($field->isRequired())->toBeFalse()
        ->and((string) json_encode($form->normalize()))->not->toContain('Closure');
});

it('evaluates conditions server-side', function (): void {
    $state = new FormState(['kind' => 'elective', 'level' => 3, 'tags' => ['1', '2'], 'note' => '', 'items' => ['iaaaaaaaaaa' => ['title' => 'x']]]);

    expect(Condition::make('kind', 'elective')->passes($state))->toBeTrue()
        ->and(Condition::make('kind', 'core')->passes($state))->toBeFalse()
        ->and(Condition::make('level', [1, 3])->passes($state))->toBeTrue()
        ->and(Condition::make('tags', 2)->passes($state))->toBeTrue()
        ->and(Condition::not('kind', 'core')->passes($state))->toBeTrue()
        ->and(Condition::filled('note')->passes($state))->toBeFalse()
        ->and(Condition::blank('note')->passes($state))->toBeTrue()
        ->and(Condition::filled('title')->passes($state, 'items.iaaaaaaaaaa', ['title']))->toBeTrue()
        ->and(Condition::using(fn (FormState $s): bool => $s->get('level') > 2, ['level'])->passes($state))->toBeTrue()
        ->and(Condition::using(fn (): string => 'yes')->passes($state))->toBeFalse(); // @phpstan-ignore argument.type
});

it('normalizes untrusted input without inventing values', function (Field $field, mixed $input, mixed $expected): void {
    expect($field->normalizeInput($input, new FormsConfig))->toBe($expected);
})->with([
    'trimmed text' => [new TextInput('x'), '  Algebra ', 'Algebra'],
    'empty text is null' => [new TextInput('x'), '   ', null],
    'integer' => [new NumberInput('x'), '42', 42],
    'decimal' => [new NumberInput('x'), '4.5', 4.5],
    'empty number is null, not zero' => [new NumberInput('x'), '', null],
    'invalid number kept for validation' => [new NumberInput('x'), '4abc', '4abc'],
    'checkbox on' => [new Checkbox('x'), '1', true],
    'checkbox off' => [new Toggle('x'), null, false],
    'checkbox garbage kept' => [new Checkbox('x'), 'maybe', 'maybe'],
    'password untouched' => [new PasswordInput('x'), ' secret ', ' secret '],
    'multiple select' => [new Select('x', new Property(Select::Multiple, true), new Options(['a' => 'A'])), ['a', '', 'a', 'b'], ['a', 'b']],
]);

it('handles dates without moving calendar days and converts date-times by zone', function (): void {
    $config = new FormsConfig(timezone: 'Africa/Douala');
    $at = new DateTimePicker('at');

    expect((new DatePicker('on'))->hydrate(Carbon::parse('2026-03-01 00:00:00', 'UTC'), $config))->toBe('2026-03-01')
        ->and($at->hydrate(Carbon::parse('2026-03-01 09:30:00', 'UTC'), $config))->toBe('2026-03-01T10:30')
        ->and($at->dehydrate('2026-03-01T10:30', $config))->toBe('2026-03-01 09:30:00')
        ->and((new TimePicker('t'))->hydrate('09:15:00', $config))->toBe('09:15');
});

it('never hydrates passwords', function (): void {
    expect((new PasswordInput('password'))->hydrate('$2y$hash', new FormsConfig))->toBeNull()
        ->and((new PasswordInput('password'))->skipsEmpty())->toBeTrue();
});

it('sanitizes rich HTML on the server', function (): void {
    $html = '<p onclick="steal()">Hi <strong>there</strong><script>alert(1)</script></p><a href="javascript:alert(1)">x</a><a href="https://gcego.test">ok</a><iframe src="//evil"></iframe><img src=x onerror=alert(1)>';
    $clean = HtmlSanitizer::sanitize($html, RichEditor::DefaultElements);

    expect($clean)->toContain('<strong>there</strong>')
        ->and($clean)->toContain('href="https://gcego.test"')
        ->and($clean)->not->toContain('script')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('javascript:')
        ->and($clean)->not->toContain('iframe')
        ->and($clean)->not->toContain('onerror');
});

it('provides state helpers and stable item keys', function (): void {
    $state = new FormState(['a' => ['b' => 1]]);
    $key = StatePath::newItemKey();

    expect($state->get('a.b'))->toBe(1)
        ->and($state->has('a.c'))->toBeFalse()
        ->and($state->with('a.c', 2)->get('a.c'))->toBe(2)
        ->and($state->get('a.c'))->toBeNull()
        ->and(StatePath::isItemKey($key))->toBeTrue()
        ->and(StatePath::isItemKey('0'))->toBeFalse()
        ->and($key)->not->toBe(StatePath::newItemKey())
        ->and(fn () => new Rules('required|string'))->toThrow(InvalidFieldException::class)
        ->and(fn () => new Options(stdClass::class))->toThrow(InvalidFieldException::class);
});

it('validates configuration', function (): void {
    expect(FormsConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-forms.php'))->toEqual(new FormsConfig)
        ->and(fn () => FormsConfig::fromArray(['options' => ['limit' => 500]]))->toThrow(InvalidFieldException::class)
        ->and(fn () => FormsConfig::fromArray(['state' => ['path' => 'Data']]))->toThrow(InvalidFieldException::class)
        ->and(fn () => FormsConfig::fromArray(['uploads' => ['disks' => []]]))->toThrow(InvalidFieldException::class);
});
