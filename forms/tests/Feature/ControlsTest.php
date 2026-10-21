<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Fields\Checkbox;
use Gcego\Forms\Fields\CheckboxList;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\DateTimePicker;
use Gcego\Forms\Fields\Radio;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\TimePicker;
use Gcego\Forms\Form;
use Gcego\Forms\Layout\Section;

it('replaces the native select with a listbox that drives a hidden select carrying the binding', function (): void {
    $output = html(new Form(new Select('kind', 'Kind', new Options(['a' => 'Science', 'b' => 'Arts']), new Property(Select::Searchable, true))));

    expect($output)->toContain('x-data="gcegoListbox(')
        ->and($output)->toContain('role="listbox"')
        ->and($output)->toContain('aria-haspopup="listbox"')
        ->and($output)->toContain('aria-label="Search…"')
        ->and((bool) preg_match('/<select x-ref="native" name="kind"[^>]*tabindex="-1" aria-hidden="true"/', $output))->toBeTrue()
        ->and($output)->toContain('data-gcego-controls');
});

it('replaces native date, date-time and time inputs with the picker in the field wire format', function (string $field, string $mode): void {
    $output = html(new Form(new $field('at', 'At')));

    expect($output)->toContain('data-gcego-date-picker="'.$mode.'"')
        ->and($output)->toContain('aria-haspopup="dialog"')
        ->and($output)->toContain('<input type="hidden" x-ref="native" name="at"')
        ->and($output)->not->toContain('type="date"')
        ->and($output)->not->toContain('type="time"')
        ->and($output)->not->toContain('datetime-local');
})->with([
    'date' => [DatePicker::class, 'date'],
    'date and time' => [DateTimePicker::class, 'datetime'],
    'time' => [TimePicker::class, 'time'],
]);

it('keeps real checkboxes and radios, drawn as themed controls', function (): void {
    $output = html(new Form(
        new Checkbox('agree', 'Agree'),
        new CheckboxList('days', 'Days', new Options(['mon' => 'Monday'])),
        new Radio('size', 'Size', new Options(['s' => 'Small'])),
    ));

    expect($output)->toContain('type="checkbox" name="agree"')
        ->and($output)->toContain('type="radio" name="size"')
        ->and(substr_count($output, 'appearance-none'))->toBe(3)
        ->and($output)->toContain('has-checked:bg-primary-soft');
});

it('renders a section icon badge and description in the section header', function (): void {
    $output = html(new Form(new Section('Guardian', new Property(Section::Icon, 'heroicon:users'), new Property(Section::Description, 'Who the school contacts.'), new TextInput('guardian_name'))));

    expect($output)->toContain('rounded-xl bg-primary-soft')
        ->and($output)->toContain('<svg')
        ->and($output)->toContain('Who the school contacts.');
});
