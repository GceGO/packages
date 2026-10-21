<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Evaluation\Get;
use Gcego\Components\Evaluation\Set;
use Gcego\Components\Facades\Components;
use Gcego\Components\Patching\SchemaPatch;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\FormSubmitted;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Forms\State\FormState;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function customerForm(): Form
{
    return Form::make()->schema([
        Select::make('type')->options(['person' => 'Person', 'business' => 'Business'])->default('person'),
        TextInput::make('company')->visible(fn (Get $get): bool => $get('type') === 'business'),
        TextInput::make('vat')->required(fn (Get $get): bool => $get('type') === 'business'),
        TextInput::make('nickname')->hidden(fn (Get $get): bool => $get('type') === 'business'),
        TextInput::make('code')->disabled(fn (string $operation): bool => $operation === 'edit'),
        Section::make('Extra')->visible(fn (Get $get): bool => $get('type') === 'business')->schema([
            TextInput::make('website'),
        ]),
    ])->build();
}

it('builds forms Filament-style with make()', function (): void {
    $form = customerForm();

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->validate()->isValid())->toBeTrue()
        ->and(forms()->schema($form)->paths())->toBe(['type', 'company', 'vat', 'nickname', 'code', 'website']);
});

it('keeps hidden fields, and fields in hidden layouts, out of processed data', function (): void {
    $data = forms()->process(customerForm(), ['type' => 'person', 'company' => 'Acme', 'nickname' => 'Jo', 'website' => 'acme.test']);

    expect($data)->toBe(['type' => 'person', 'nickname' => 'Jo']);
});

it('includes fields once their closures make them visible, and drops those hidden by hidden()', function (): void {
    $data = forms()->process(customerForm(), ['type' => 'business', 'company' => 'Acme', 'vat' => 'CM1', 'nickname' => 'Jo', 'website' => 'acme.test']);

    expect($data)->toBe(['type' => 'business', 'company' => 'Acme', 'vat' => 'CM1', 'website' => 'acme.test']);
});

it('enforces closure requirements during validation', function (): void {
    expect(fn () => forms()->process(customerForm(), ['type' => 'business']))->toThrow(ValidationException::class)
        ->and(forms()->process(customerForm(), ['type' => 'person']))->toBe(['type' => 'person']);
});

it('gives closures the record and the operation, and ignores values of fields they disable', function (): void {
    $subject = $this->world['subject'];

    expect(forms()->process(customerForm(), ['type' => 'person', 'code' => 'NEW'], $subject))->not->toHaveKey('code')
        ->and(forms()->process(customerForm(), ['type' => 'person', 'code' => 'NEW']))->toHaveKey('code', 'NEW');
});

it('evaluates closure defaults when hydrating', function (): void {
    $form = Form::make()->schema([
        TextInput::make('code')->default(fn (string $operation): string => $operation === 'create' ? 'NEW-' : ''),
    ])->build();

    expect(forms()->hydrate($form))->toBe(['code' => 'NEW-']);
});

it('resolves relative paths inside repeater items', function (): void {
    $form = Form::make()->schema([
        Repeater::make('objectives')->schema([
            TextInput::make('title'),
            TextInput::make('details')->visible(fn (Get $get): bool => $get('title') === 'Show'),
        ]),
    ])->build();

    $data = forms()->process($form, ['objectives' => ['ia1b2c3d4e5' => ['title' => 'Show', 'details' => 'kept'], 'if1e2d3c4b5' => ['title' => 'Hide', 'details' => 'dropped']]]);

    expect(array_values($data['objectives']))->toBe([['title' => 'Show', 'details' => 'kept'], ['title' => 'Hide']]);
});

it('discovers live fields from what closures read, including inside repeaters', function (): void {
    $form = Form::make()->schema([
        Select::make('type')->options(['person' => 'Person', 'business' => 'Business']),
        TextInput::make('company')->visible(fn (Get $get): bool => $get('type') === 'business'),
        Repeater::make('objectives')->schema([
            TextInput::make('title'),
            TextInput::make('details')->visible(fn (Get $get): bool => filled($get('title'))),
        ]),
    ])->build();
    $schema = forms()->schema($form);

    $live = forms()->livePaths($schema, new FormState(['objectives' => ['ia1b2c3d4e5' => ['title' => 'x']]]));

    expect($live)->toBe(['type', 'objectives.*.title'])
        ->and($schema->live)->toBe([]);
});

it('renders the fields closures read with live bindings in the Livewire host', function (): void {
    app(Registry::class)->register(new Blueprint('customers', new Ability('viewAny', Subject::class), customerForm()));

    Livewire::test(FormHost::class, ['definition' => 'customers'])
        ->assertSeeHtml('wire:model.live="data.type"')
        ->assertDontSeeHtml('wire:model.live.debounce.400ms="data.nickname"')
        ->assertDontSee('Company')
        ->set('data.type', 'business')
        ->assertSee('Company')
        ->assertSee('Website');
});

it('runs afterStateUpdated with the new and old state', function (): void {
    app(Registry::class)->register(new Blueprint('articles', new Ability('viewAny', Subject::class), Form::make()->schema([
        TextInput::make('title')->afterStateUpdated(fn (Set $set, ?string $state, ?string $old) => $set('slug', strtolower((string) $state).'|'.$old)),
        TextInput::make('slug'),
    ])->build()));

    Livewire::test(FormHost::class, ['definition' => 'articles'])
        ->set('data.title', 'First')
        ->assertSet('data.slug', 'first|')
        ->set('data.title', 'Second')
        ->assertSet('data.slug', 'second|First');
});

it('refuses afterStateUpdated writes to undeclared fields', function (): void {
    app(Registry::class)->register(new Blueprint('articles', new Ability('viewAny', Subject::class), Form::make()->schema([
        TextInput::make('title')->afterStateUpdated(fn (Set $set) => $set('is_admin', true)),
    ])->build()));

    expect(fn () => Livewire::test(FormHost::class, ['definition' => 'articles'])->set('data.title', 'x'))
        ->toThrow(InvalidStateException::class);
});

it('lets other modules extend a blueprint form through Components::extend()', function (): void {
    Event::fake([FormSubmitted::class]);
    Components::extend('contact.form', fn (SchemaPatch $patch): SchemaPatch => $patch
        ->insertAfter('message', TextInput::make('phone'))
        ->modify('message', fn (ComponentBuilder $message): ComponentBuilder => $message->required()));
    app(Registry::class)->register(new Blueprint('contact', new Ability('viewAny', Subject::class), Form::make()->schema([TextInput::make('message')])->build()));

    $host = Livewire::test(FormHost::class, ['definition' => 'contact'])->assertSee('Phone');
    $host->call('submit')->assertHasErrors(['data.message']);
    $host->set('data.message', 'Hello')->set('data.phone', '+237 600')->call('submit')->assertHasNoErrors();

    Event::assertDispatched(FormSubmitted::class, fn (FormSubmitted $event): bool => $event->data === ['message' => 'Hello', 'phone' => '+237 600']);
});
