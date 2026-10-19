<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\FormSubmitted;
use Gcego\Forms\Layout\Step;
use Gcego\Forms\Layout\Wizard;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Forms\State\FormState;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('creates records through the Create action', function (): void {
    Livewire::test(FormHost::class, ['definition' => 'simple'])
        ->assertSet('action', 'create')
        ->assertSee('Subject Name')
        ->set('data.name', 'Chemistry')
        ->set('data.code', 'CHM101')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('gcego-action-completed')
        ->assertSet('data.name', null);

    $subject = Subject::query()->where('code', 'CHM101')->firstOrFail();

    expect($subject->name)->toBe('Chemistry')
        ->and($subject->institution_id)->toBe(1);
});

it('shows validation errors with an accessible summary and keeps state', function (): void {
    Livewire::test(FormHost::class, ['definition' => 'simple'])
        ->set('data.name', '')
        ->set('data.code', 'X1')
        ->call('submit')
        ->assertHasErrors(['data.name'])
        ->assertSee('role="alert"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('There is 1 problem with this form.')
        ->assertDontSee('[2,*]')
        ->assertSet('data.code', 'X1');

    expect(Subject::query()->where('code', 'X1')->exists())->toBeFalse();
});

it('edits records through the Edit action and syncs many-to-many fields', function (): void {
    $subject = $this->world['subject'];

    Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => $subject->id])
        ->assertSet('action', 'edit')
        ->assertSet('data.name', 'Algebra')
        ->assertSet('data.password', null)
        ->assertDontSee('hashed-value')
        ->assertDontSee('TOP SECRET')
        ->set('data.name', 'Linear Algebra')
        ->set('data.kind', 'core')
        ->set('data.credits', '4')
        ->set('data.tags', [(string) $this->world['tags']['lab']->id])
        ->call('submit')
        ->assertHasNoErrors();

    $subject->refresh();

    expect($subject->name)->toBe('Linear Algebra')
        ->and($subject->credits)->toBe(4.0)
        ->and($subject->secret_notes)->toBe('TOP SECRET')
        ->and($subject->getRawOriginal('password'))->toBe('hashed-value')
        ->and($subject->tags()->pluck('tags.id')->all())->toBe([$this->world['tags']['lab']->id]);
});

it('adds, removes and reorders repeater items on the server', function (): void {
    $host = Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => $this->world['subject']->id]);
    $first = array_key_first($host->get('data.objectives'));

    $host->call('addItem', 'objectives');
    $keys = array_keys($host->get('data.objectives'));
    $host->set('data.objectives.'.$keys[1].'.title', 'Matrices')
        ->call('moveItem', 'objectives', $keys[1], -1);

    expect(array_keys($host->get('data.objectives')))->toBe([$keys[1], $first]);

    $host->call('removeItem', 'objectives', $first);

    expect(array_values($host->get('data.objectives')))->toBe([['title' => 'Matrices']]);

    for ($i = 0; $i < 10; $i++) {
        $host->call('addItem', 'objectives');
    }

    expect($host->get('data.objectives'))->toHaveCount(5);
});

it('shows conditional fields reactively', function (): void {
    Livewire::test(FormHost::class, ['definition' => 'subjects'])
        ->assertSeeHtml('wire:model.live="data.kind"')
        ->call('addItem', 'objectives')
        ->assertSeeHtml('wire:model.live.debounce.400ms="data.objectives.')
        ->assertDontSee('Track')
        ->set('data.kind', 'elective')
        ->assertSee('Track')
        ->set('data.kind', 'core')
        ->assertDontSee('Track');
});

it('rejects updates to undeclared state and forged repeater keys', function (string $property): void {
    expect(fn () => Livewire::test(FormHost::class, ['definition' => 'simple'])->set($property, 'x'))
        ->toThrow(InvalidStateException::class);
})->with(['data.institution_id', 'data.name.nested', 'data.objectives.0.title', 'data']);

it('locks the definition, action, record and step', function (string $property, mixed $value): void {
    expect(fn () => Livewire::test(FormHost::class, ['definition' => 'simple'])->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([['definition', 'subjects'], ['action', 'delete'], ['record', 1], ['step', 3]]);

it('refuses forged mass-assignment keys on submit', function (): void {
    Livewire::test(FormHost::class, ['definition' => 'simple'])
        ->set('data.name', 'Forged')
        ->call('submit')
        ->assertHasNoErrors();

    $host = Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => $this->world['subject']->id]);
    $host->set('data.locked_code', 'HACK')->set('data.kind', 'core')->set('data.credits', '1')->call('submit');

    expect($this->world['subject']->refresh()->getAttribute('locked_code'))->toBeNull()
        ->and($this->world['subject']->institution_id)->toBe(1);
});

it('denies users without the action ability', function (): void {
    actAs($this->world['reader']);

    Livewire::test(FormHost::class, ['definition' => 'simple'])->assertForbidden();
});

it('treats records of another tenant like missing ones', function (): void {
    Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => $this->world['south']->id])->assertForbidden();
    Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => 999999])->assertForbidden();
});

it('walks wizard steps with per-step validation', function (): void {
    $blueprint = new Blueprint('wizard', new Ability('viewAny', Subject::class), new Source(Subject::class), new Form(new Wizard(
        new Step('Basics', new TextInput('name', 'Subject Name', new Property(TextInput::Required, true))),
        new Step('Details', new TextInput('code', 'Subject Code', new Property(TextInput::Required, true))),
    )), new Create);
    app(Registry::class)->register($blueprint);

    Livewire::test(FormHost::class, ['definition' => 'wizard'])
        ->assertDontSee('Subject Code')
        ->assertDontSee('type="submit"', false)
        ->call('nextStep')
        ->assertHasErrors(['data.name'])
        ->assertSet('step', 0)
        ->set('data.name', 'Music')
        ->call('nextStep')
        ->assertSet('step', 1)
        ->assertSee('Subject Code')
        ->call('submit')
        ->assertHasErrors(['data.code'])
        ->set('data.code', 'MUS101')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Subject::query()->where('code', 'MUS101')->value('name'))->toBe('Music');
});

it('dispatches validated data for standalone forms', function (): void {
    Event::fake([FormSubmitted::class]);
    app(Registry::class)->register(new Blueprint('contact', new Ability('viewAny', Subject::class), new Form(new TextInput('message', new Property(TextInput::Required, true)))));

    Livewire::test(FormHost::class, ['definition' => 'contact'])
        ->assertSet('action', null)
        ->set('data.message', '  Hello  ')
        ->call('submit')
        ->assertDispatched('gcego-form-submitted');

    Event::assertDispatched(FormSubmitted::class, fn (FormSubmitted $event): bool => $event->data === ['message' => 'Hello'] && $event->definition === 'blueprint:contact');
});

it('recomputes dependent options and rejects stale choices', function (): void {
    $topics = ['core' => ['algebra' => 'Algebra'], 'elective' => ['music' => 'Music']];
    app(Registry::class)->register(new Blueprint('topics', new Ability('viewAny', Subject::class), new Form(
        new Select('kind', new Options(['core' => 'Core', 'elective' => 'Elective'])),
        new Select('topic', new Options(fn (FormState $state): array => $topics[$state->get('kind')] ?? [], dependsOn: ['kind'])),
    )));

    $host = Livewire::test(FormHost::class, ['definition' => 'topics'])
        ->set('data.kind', 'core')
        ->assertSee('Algebra')
        ->assertDontSee('Music')
        ->set('data.kind', 'elective')
        ->assertSee('Music')
        ->assertDontSee('Algebra');

    $host->set('data.topic', 'algebra')->call('submit')->assertHasErrors(['data.topic']);
    $host->set('data.topic', 'music')->call('submit')->assertHasNoErrors();
});

it('stores Livewire uploads privately on submit', function (): void {
    Storage::fake('local');
    $file = UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf');

    Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => $this->world['subject']->id])
        ->set('data.kind', 'core')
        ->set('data.credits', '2')
        ->set('data.syllabus', $file)
        ->call('submit')
        ->assertHasNoErrors();

    $path = $this->world['subject']->refresh()->getAttribute('syllabus');

    expect($path)->toStartWith('gcego-uploads/');
    Storage::disk('local')->assertExists($path);
});
