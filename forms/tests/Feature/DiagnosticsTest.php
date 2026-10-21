<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Core\Gcego;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Relationship;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Diagnostics\FormInspector;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Runtime\Rendering\RendererRegistry;
use Livewire\Livewire;

it('reports a healthy form with its fields', function (): void {
    $report = app(FormInspector::class)->inspect(subjectsForm(), Subject::class);

    expect($report->isHealthy())->toBeTrue()
        ->and(array_column($report->fields, 'path'))->toContain('objectives.*.title')
        ->and((string) json_encode($report->toArray()))->not->toContain('TOP SECRET');
});

it('reports broken definitions', function (): void {
    $report = app(FormInspector::class)->inspect(new Form(
        new Select('a'),
        new Select('b', new Relationship('notOptions')),
        new FileUpload('c', new Property(FileUpload::Disk, 's3')),
        new Select('d', new Options(fn () => ['x' => 'X'])),
    ), Subject::class);
    $checks = array_map(fn ($d) => $d->check, $report->diagnostics);

    expect($report->isHealthy())->toBeFalse()
        ->and($checks)->toContain('options', 'relationship', 'uploads', 'reactive')
        ->and(app(FormInspector::class)->inspect(new Form(new TextInput('a', new VisibleWhen('a', 1))))->errors()[0]->check)->toBe('schema');
});

it('registers renderers, Livewire, the package descriptor and config', function (): void {
    actAs(world()['editor']);

    expect(app(RendererRegistry::class)->has(new TextInput('x')))->toBeTrue()
        ->and(app(RendererRegistry::class)->has(new Form(new TextInput('x'))))->toBeTrue()
        ->and(app(Gcego::class)->hasPackage('gcego/forms'))->toBeTrue()
        ->and(config('gcego-forms.options.limit'))->toBe(100)
        ->and(Livewire::test(FormsServiceProvider::LivewireAlias, ['definition' => 'simple'])->instance())->toBeInstanceOf(FormHost::class);
});
