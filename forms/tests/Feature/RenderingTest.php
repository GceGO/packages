<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Forms\Configuration\Access;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Relationship;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\Textarea;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\Layout\Grid;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Layout\Step;
use Gcego\Forms\Layout\Tab;
use Gcego\Forms\Layout\Tabs;
use Gcego\Forms\Layout\Wizard;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Security\Permissions\Permission;

it('renders the documented examples', function (Form $form, array $expected): void {
    $output = html($form);

    foreach ($expected as $fragment) {
        expect($output)->toContain($fragment);
    }
})->with([
    'fields' => [new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Subject Code')), ['Subject Name', 'Subject Code', 'name="name"', 'name="code"', 'type="text"']],
    'section' => [new Form(new Section('Basic Information', new TextInput('name', 'Subject Name'), new Textarea('description', 'Description'))), ['<section', 'Basic Information</h3>', 'aria-labelledby=', '<textarea']],
    'grid' => [new Form(new Grid(2, new TextInput('name', 'Subject Name'), new Select('category', 'Category', new Options(['a' => 'Science'])))), ['md:grid-cols-2', '<select', 'Science</option>']],
    'repeater' => [new Form(new Repeater('objectives', new TextInput('title', 'Objective'), new Textarea('description', 'Description'), new Property(Repeater::MinItems, 1))), ['<fieldset', 'Objectives</legend>', 'Objective', 'name="objectives[']],
]);

it('renders accessible labels, descriptions and required markers', function (): void {
    $output = html(new Form(new TextInput('name', 'Subject Name', new Property(TextInput::Required, true), new Property(TextInput::HelperText, 'As printed on reports'))));

    preg_match('/<input id="([^"]+)"/', $output, $match);

    expect($match)->toHaveKey(1)
        ->and($output)->toContain('<label for="'.$match[1].'"')
        ->and($output)->toContain('aria-describedby="'.$match[1].'-helper"')
        ->and($output)->toContain('aria-required="true"')
        ->and($output)->toContain('As printed on reports');
});

it('escapes labels, values and options', function (): void {
    $payload = '<script>alert(1)</script>';
    $output = html(new Form(new TextInput('name', $payload, new Property(TextInput::Default, '"><img src=x onerror=alert(1)>')), new Select('kind', 'Kind', new Options(['x' => $payload]))));

    expect($output)->not->toContain('<script>')
        ->and($output)->not->toContain('<img')
        ->and($output)->toContain('&lt;script&gt;');
});

it('renders tabs and wizards with ARIA semantics', function (): void {
    $tabs = html(new Form(new Tabs(new Tab('General', new TextInput('a')), new Tab('Advanced', new TextInput('b')))));
    $wizard = html(new Form(new Wizard(new Step('First', new TextInput('a', 'Alpha')), new Step('Second', new TextInput('b', 'Beta')))));

    expect($tabs)->toContain('role="tablist"')
        ->and(substr_count($tabs, 'role="tab"'))->toBe(2)
        ->and(substr_count($tabs, 'role="tabpanel"'))->toBe(2)
        ->and($wizard)->toContain('aria-current="step"')
        ->and($wizard)->toContain('Alpha')
        ->and($wizard)->not->toContain('Beta');
});

it('never renders hidden fields, passwords or unreadable values', function (): void {
    $world = world();
    actAs($world['editor']);
    $output = html(new Form(
        new TextInput('name', 'Subject Name'),
        new Hidden('origin', new Property(Hidden::Default, 'studio')),
        new PasswordInput('password', 'Password'),
        new Textarea('secret_notes', 'Secret notes', new Access(read: new Permission('subjects.secrets'))),
    ));

    expect($output)->not->toContain('name="origin"')
        ->and($output)->not->toContain('studio')
        ->and($output)->not->toContain('Secret notes')
        ->and($output)->toContain('type="password"')
        ->and((bool) preg_match('/<input[^>]*type="password"[^>]*value=/', $output))->toBeFalse();
});

it('fails clearly when relationship options have no model', function (): void {
    expect(fn () => html(new Form(new Select('category_id', 'Category', new Relationship('category')))))
        ->toThrow(RenderException::class, 'relationship options need a form with a Source model');
});

it('applies styling slots', function (): void {
    expect(html(new Form(new TextInput('name'))))->toContain('rounded-gcego');
});

it('renders without queries for static options', function (): void {
    $form = new Form(new TextInput('name'), new Select('kind', new Options(['a' => 'A'])));

    expect(queriesDuring(fn () => html($form)))->toBe([]);
});
