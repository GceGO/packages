<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests\Fixtures;

use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Access;
use Gcego\Forms\Configuration\DisabledWhen;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Relationship;
use Gcego\Forms\Configuration\RequiredWhen;
use Gcego\Forms\Configuration\Rules;
use Gcego\Forms\Configuration\Validate;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Fields\Checkbox;
use Gcego\Forms\Fields\CheckboxList;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\DateTimePicker;
use Gcego\Forms\Fields\EmailInput;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\Fields\NumberInput;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Radio;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Fields\RichEditor;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\Textarea;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\TimePicker;
use Gcego\Forms\Fields\Toggle;
use Gcego\Forms\Form;
use Gcego\Forms\Layout\Grid;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Persistence\SaveRecord;
use Gcego\Forms\State\FormState;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

final class Blueprints
{
    public static function form(): Form
    {
        return new Form(
            new Section(
                'Basic Information',
                new Grid(
                    2,
                    new TextInput('name', 'Subject Name', new Property(TextInput::Required, true), new Property(TextInput::MaxLength, 120)),
                    new TextInput('code', 'Subject Code', new Property(TextInput::Pattern, '[A-Z]{3}[0-9]{3}'), new Rules(new UppercaseRule, fn (?Subject $record) => [Rule::unique('subjects', 'code')->ignore($record)])),
                ),
                new Textarea('description', 'Description', new Property(Textarea::MaxLength, 500)),
            ),
            new EmailInput('email', 'Contact Email'),
            new Select('kind', 'Kind', new Options(Kind::class)),
            new Radio('track', 'Track', new Options(['science' => 'Science', 'arts' => 'Arts']), new VisibleWhen('kind', 'elective')),
            new NumberInput('level', 'Level', new Property(NumberInput::Integer, true), new Property(NumberInput::Min, 1), new Property(NumberInput::Max, 7)),
            new NumberInput('credits', 'Credits', new RequiredWhen('kind', 'core')),
            new Toggle('is_published', 'Published'),
            new Checkbox('agreed', 'I agree'),
            new DatePicker('starts_on', 'Starts on', new Property(DatePicker::Min, '2020-01-01')),
            new DateTimePicker('published_at', 'Published at'),
            new TimePicker('lesson_time', 'Lesson time'),
            new Select('category_id', 'Category', new Relationship('category', query: fn (Builder $query) => $query->where('active', true))),
            new CheckboxList('tags', 'Tags', new Relationship('tags', 'label')),
            new RichEditor('body', 'Body'),
            new Repeater('objectives', new TextInput('title', 'Objective', new Property(TextInput::Required, true)), new Textarea('details', 'Details', VisibleWhen::filled('title')), new Property(Repeater::MaxItems, 5)),
            new FileUpload('syllabus', 'Syllabus', new Property(FileUpload::Accept, ['pdf', 'txt']), new Property(FileUpload::MaxSize, 100)),
            new PasswordInput('password', 'Password', new Property(PasswordInput::Confirmed, true)),
            new Textarea('secret_notes', 'Secret notes', new Access(read: new Permission('subjects.secrets'), write: new Permission('subjects.secrets'))),
            new TextInput('locked_code', 'Locked', new Property(TextInput::Readonly, true)),
            new TextInput('internal_flag', 'Internal', new DisabledWhen('kind', 'core')),
            new Hidden('origin', new Property(Hidden::Default, 'studio')),
            new Validate(function ($validator, FormState $state): void {
                if ($state->get('kind') === 'elective' && $state->get('level') !== null && $state->get('level') > 5) {
                    $validator->errors()->add('level', 'Electives stop at level 5.');
                }
            }),
        );
    }

    public static function subjects(): Blueprint
    {
        return new Blueprint('subjects', new Ability('viewAny', Subject::class), new Source(Subject::class), self::form(), new Create, new Edit(new Handler(SaveRecord::class)), new Delete);
    }

    public static function simple(): Blueprint
    {
        return new Blueprint('simple', new Ability('viewAny', Subject::class), new Source(Subject::class), new Form(new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)), new TextInput('code', 'Subject Code')), new Create, new Edit);
    }
}
