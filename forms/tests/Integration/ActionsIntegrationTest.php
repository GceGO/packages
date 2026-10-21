<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\ActionManager;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Handlers\UpdateRecord;
use Gcego\Actions\Livewire\ActionHost;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\UsesForm;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\Integration\FormInputResolver;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Forms\Tests\Fixtures\ShoutField;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function execute(string $action, array $input, string|int|null $record = null, string $definition = 'subjects'): ActionResult
{
    return app(ActionManager::class)->execute(new ActionRequest($definition, $action, $record, input: $input));
}

it('registers the form input resolver for Create and Edit', function (): void {
    $manager = app(ActionManager::class);

    expect(app(FormInputResolver::class)->supports($manager->resolve('subjects', 'create')))->toBeTrue()
        ->and(app(FormInputResolver::class)->supports($manager->resolve('subjects', 'delete')))->toBeFalse()
        ->and($manager->initialInput($manager->resolve('subjects', 'edit'), $this->world['subject']->id)['name'])->toBe('Algebra');
});

it('creates through the action pipeline with form validation', function (): void {
    $invalid = execute('create', valid(['name' => '']));
    $created = execute('create', valid(['objectives' => ['iaaaaaaaaaa' => ['title' => 'Cells']]]));
    $subject = Subject::query()->where('code', 'BIO101')->firstOrFail();

    expect($invalid->status)->toBe(ActionStatus::ValidationFailed)
        ->and(array_keys($invalid->errors))->toBe(['name'])
        ->and($created->status)->toBe(ActionStatus::Success)
        ->and($subject->institution_id)->toBe(1)
        ->and($subject->objectives)->toBe([['title' => 'Cells']])
        ->and($subject->getAttribute('origin'))->toBe('studio');
});

it('denies forged keys instead of mass-assigning them', function (): void {
    $result = execute('create', valid(['institution_id' => 2, 'id' => 77]));

    expect($result->status)->toBe(ActionStatus::Denied)
        ->and($result->httpStatus())->toBe(403)
        ->and(Subject::query()->where('code', 'BIO101')->exists())->toBeFalse();
});

it('edits through SaveRecord and syncs only permitted related ids', function (): void {
    $subject = $this->world['subject'];
    $lab = $this->world['tags']['lab'];

    expect(execute('edit', valid(['code' => 'MTH101', 'tags' => ['9999']]), $subject->id)->status)->toBe(ActionStatus::ValidationFailed)
        ->and(execute('edit', valid(['code' => 'MTH101', 'tags' => [(string) $lab->id], 'category_id' => (string) $this->world['categories']['south']->id]), $subject->id)->status)->toBe(ActionStatus::ValidationFailed)
        ->and(execute('edit', valid(['code' => 'MTH101', 'tags' => [(string) $lab->id]]), $subject->id)->status)->toBe(ActionStatus::Success)
        ->and($subject->tags()->pluck('tags.id')->all())->toBe([$lab->id])
        ->and($subject->refresh()->name)->toBe('Biology');
});

it('keeps fields the submission leaves out', function (): void {
    $subject = $this->world['subject'];

    execute('edit', ['name' => 'Renamed', 'code' => 'MTH101', 'kind' => 'elective', 'track' => 'arts'], $subject->id);

    expect($subject->refresh()->name)->toBe('Renamed')
        ->and($subject->getRawOriginal('password'))->toBe('hashed-value')
        ->and($subject->tags()->count())->toBe(1)
        ->and($subject->objectives)->toBe([['title' => 'Equations', 'details' => 'Linear']]);
});

it('cannot edit records of another tenant or without permission', function (): void {
    expect(execute('edit', valid(), $this->world['south']->id)->status)->toBe(ActionStatus::NotFound);

    actAs($this->world['reader']);

    expect(execute('edit', valid(['code' => 'MTH101']), $this->world['subject']->id)->status)->toBe(ActionStatus::Denied)
        ->and($this->world['subject']->refresh()->name)->toBe('Algebra');
});

it('renders the full form in ActionHost dialogs and submits it', function (): void {
    $host = Livewire::test(ActionHost::class, ['definition' => 'simple'])->call('run', 'create');

    expect($host->html())->toContain('wire:model="input.name"')
        ->and($host->html())->toContain('Subject Code');

    $host->set('input.name', '')->call('submit')->assertHasErrors(['input.name']);

    expect($host->html())->toContain('aria-invalid="true"');

    $host->set('input.name', 'Geography')->set('input.code', 'GEO101')->call('submit')->assertHasNoErrors();

    expect(Subject::query()->where('code', 'GEO101')->value('name'))->toBe('Geography');
});

it('gives custom actions form input with UsesForm', function (): void {
    app(Registry::class)->register(new Blueprint(
        'renaming',
        new Ability('viewAny', Subject::class),
        new Source(Subject::class),
        new Form(new TextInput('name', new Property(TextInput::Required, true))),
        new Action('rename', 'Rename', new Property(Action::Ability, 'update'), new Property(Action::Record, true), new UsesForm, new Handler(UpdateRecord::class)),
    ));
    $subject = $this->world['subject'];

    expect(app(FormInputResolver::class)->supports(app(ActionManager::class)->resolve('renaming', 'rename')))->toBeTrue()
        ->and(execute('rename', ['name' => ''], $subject->id, 'renaming')->status)->toBe(ActionStatus::ValidationFailed)
        ->and(execute('rename', ['name' => 'X', 'code' => 'HACK'], $subject->id, 'renaming')->status)->toBe(ActionStatus::Denied)
        ->and(execute('rename', ['name' => 'Renamed'], $subject->id, 'renaming')->status)->toBe(ActionStatus::Success)
        ->and($subject->refresh()->name)->toBe('Renamed')
        ->and($subject->code)->toBe('MTH101');
});

it('persists fields that are not columns after the record is saved, in the action transaction', function (): void {
    app(Registry::class)->register(new Blueprint('shouting', new Ability('viewAny', Subject::class), new Source(Subject::class), new Form(new TextInput('name', 'Name', new Property(TextInput::Required, true)), new ShoutField('shout', 'Shout')), new Create, new Edit));

    $created = execute('create', ['name' => 'Loud', 'shout' => 'hey'], definition: 'shouting');
    $subject = Subject::query()->where('name', 'Loud')->firstOrFail();
    $invalid = execute('create', ['name' => 'Quiet', 'shout' => 'forbidden'], definition: 'shouting');
    $failed = execute('create', ['name' => 'Broken', 'shout' => 'explode'], definition: 'shouting');

    expect($created->status)->toBe(ActionStatus::Success)
        ->and($subject->code)->toBe('HEY')
        ->and($invalid->status)->toBe(ActionStatus::ValidationFailed)
        ->and(array_keys($invalid->errors))->toBe(['shout'])
        ->and($failed->status)->toBe(ActionStatus::Failed)
        ->and(Subject::query()->whereIn('name', ['Quiet', 'Broken'])->exists())->toBeFalse()
        ->and(app(ActionManager::class)->initialInput(app(ActionManager::class)->resolve('shouting', 'edit'), $subject->id)['shout'])->toBe('hey');
});
