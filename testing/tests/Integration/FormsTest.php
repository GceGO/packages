<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Forms\Configuration\Access;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\Toggle;
use Gcego\Forms\Form;
use Gcego\Security\Permissions\Permission;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

function subjectForm(): Form
{
    $form = Definitions::subjects()->part('form');

    return $form instanceof Form ? $form : throw new LogicException('No form.');
}

it('fills, validates and dehydrates like a submission', function (): void {
    $world = world();

    GcegoTest::form(subjectForm(), Subject::class)
        ->actingAs($world['admin'], 1)
        ->fill(['name' => '  Mathematics  ', 'code' => 'MTH'])
        ->assertValid()
        ->assertState('name', 'Mathematics')
        ->assertState('code', 'MTH');
});

it('reports validation errors by path', function (): void {
    $world = world();

    GcegoTest::form(subjectForm(), Subject::class)->actingAs($world['admin'], 1)
        ->fill(['name' => ''])
        ->assertInvalid(['name'])
        ->assertInvalid(['name' => 'required']);

    expect(failure(fn () => GcegoTest::form(subjectForm(), Subject::class)->fill(['name' => ''])->assertValid()))->toContain('errors = ');
});

it('refuses forged fields', function (): void {
    $world = world();

    GcegoTest::form(subjectForm(), Subject::class)->actingAs($world['admin'], 1)
        ->fill(['name' => 'Physics', 'institution_id' => 2])
        ->assertRejectsUnknownField('institution_id');
});

it('drops fields the user may not write and does not load fields they may not read', function (): void {
    $world = world();
    $form = new Form(
        new TextInput('name', 'Name', new Property(TextInput::Required, true)),
        new TextInput('secret_note', 'Secret', new Access(read: Permission::inTenant('subjects.secrets'), write: Permission::inTenant('subjects.secrets'))),
    );

    GcegoTest::form($form, Subject::class)->actingAs($world['editor'], 1)
        ->record($world['algebra'])
        ->assertHydrated('name', 'Algebra')
        ->assertNotHydrated('secret_note')
        ->fill(['name' => 'Algebra', 'secret_note' => 'forged'])
        ->assertDropped('secret_note');

    GcegoTest::form($form, Subject::class)->actingAs($world['admin'], 1)->record($world['algebra'])
        ->assertHydrated('secret_note', 'algebra-secret')
        ->fill(['name' => 'Algebra', 'secret_note' => 'updated'])->assertState('secret_note', 'updated');
});

it('asserts field definitions and conditional visibility', function (): void {
    $form = new Form(
        new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
        new Toggle('graded', 'Graded'),
        new Select('scale', 'Scale', new Options(['letters' => 'Letters', 'percent' => 'Percent']), new VisibleWhen('graded')),
    );

    GcegoTest::form($form)
        ->assertHasField('name')
        ->assertMissingField('code')
        ->assertFieldLabel('name', 'Subject Name')
        ->assertFieldRequired('name')
        ->assertFieldRequired('graded', false)
        ->assertFieldDisabled('name', false)
        ->fill(['graded' => false])->assertFieldVisible('scale', false)
        ->fill(['graded' => true])->assertFieldVisible('scale')
        ->assertHealthy();
});
