<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Runtime\Exceptions\InvalidStateException;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleSearch;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('gcego-runtime.render', fn (?object $user, string $identity): bool => $identity !== 'blueprint:secret');

    $search = new ExampleSearch('search');
    app(Registry::class)->register(new Blueprint('subjects', $search, $search, new ExampleTable('table', new ExampleParagraph('Mathematics'))));
    app(Registry::class)->register(new Blueprint('secret'));
});

it('mounts a registered blueprint and renders it through the runtime', function (): void {
    Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])
        ->assertSet('blueprint', 'blueprint:subjects')
        ->assertSet('state', [
            'subjects:search' => ['query' => '', 'page' => 1],
            'subjects:search#1' => ['query' => '', 'page' => 1],
        ])
        ->assertSeeHtml('data-gcego-host="blueprint:subjects"')
        ->assertSeeHtml('wire:model.live="state.subjects:search.query"')
        ->assertSeeHtml('wire:model.live="state.subjects:search#1.query"')
        ->assertSee('Mathematics');
});

it('is available through its documented tag', function (): void {
    expect(Blade::render('<livewire:gcego-blueprint blueprint="blueprint:subjects" />'))
        ->toContain('data-gcego-blueprint="blueprint:subjects"')
        ->toContain('wire:snapshot');
});

it('updates state, re-renders and keeps sibling occurrences apart', function (): void {
    Livewire::test(BlueprintHost::class, ['blueprint' => 'blueprint:subjects'])
        ->set('state.subjects:search.query', '  algebra ')
        ->set('state.subjects:search#1.page', '4')
        ->assertSet('state.subjects:search.query', 'algebra')
        ->assertSet('state.subjects:search.page', 1)
        ->assertSet('state.subjects:search#1.query', '')
        ->assertSet('state.subjects:search#1.page', 4)
        ->assertSee('Query: [algebra], page 1')
        ->assertSee('Query: [], page 4');
});

it('escapes state values in the output', function (): void {
    Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])
        ->set('state.subjects:search.query', '<script>x</script>')
        ->assertDontSeeHtml('<script>x</script>')
        ->assertSeeHtml('&lt;script&gt;x&lt;/script&gt;');
});

it('rejects malicious state updates', function (string $property, mixed $value): void {
    expect(fn () => Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->set($property, $value))
        ->toThrow(InvalidStateException::class);
})->with([
    'replace the whole state' => ['state', ['subjects:search' => ['query' => 'x']]],
    'unknown bucket' => ['state.hacker.query', 'x'],
    'unknown field' => ['state.subjects:search.isAdmin', true],
    'invalid value' => ['state.subjects:search.page', -5],
    'oversized value' => ['state.subjects:search.query', str_repeat('x', 500)],
]);

it('locks the blueprint identity', function (): void {
    expect(fn () => Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->set('blueprint', 'blueprint:secret'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('refuses unauthorized definitions with 403', function (): void {
    Livewire::test(BlueprintHost::class, ['blueprint' => 'secret'])->assertForbidden();

    Gate::define('gcego-runtime.render', fn (?object $user, string $identity): bool => false);

    Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->assertForbidden();
});

it('fails closed when the application never defined the ability', function (): void {
    $this->rebuildApplication(['gcego-runtime.authorization.ability' => 'never-defined']);
    app(Registry::class)->register(new Blueprint('subjects'));

    Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->assertForbidden();
});

it('authorizes with the registry descriptor (identity and metadata)', function (): void {
    $seen = null;
    Gate::define('gcego-runtime.render', function (?object $user, string $identity, DefinitionDescriptor $descriptor) use (&$seen): bool {
        $seen = [$identity, $descriptor->metadata['title'] ?? null];

        return false;
    });

    Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->assertForbidden();

    expect($seen)->toBe(['blueprint:subjects', 'Subjects']);
});

it('accepts only registry identities, never class names or paths', function (string $identifier, string $exception): void {
    expectRootCause(fn () => Livewire::test(BlueprintHost::class, ['blueprint' => $identifier]), $exception);
})->with([
    'class name' => ['App\\Models\\User', RenderException::class],
    'livewire class' => [BlueprintHost::class, RenderException::class],
    'path' => ['../../etc/passwd', RenderException::class],
    'unregistered' => ['blueprint:unknown', DefinitionNotFoundException::class],
]);

it('only hosts blueprints', function (): void {
    $kinds = app(DefinitionKinds::class);
    $kinds->register(new class implements DefinitionKind
    {
        public function id(): string
        {
            return 'snippet';
        }

        public function contract(): string
        {
            return ExampleParagraph::class;
        }

        public function name(object $definition): string
        {
            return 'greeting';
        }

        public function validate(object $definition): array
        {
            return [];
        }

        public function metadata(object $definition): array
        {
            return [];
        }
    });
    app(Registry::class)->register(new ExampleParagraph('Hello'));

    expectRootCause(
        fn () => Livewire::test(BlueprintHost::class, ['blueprint' => 'snippet:greeting']),
        RenderException::class,
        'Definition "snippet:greeting" is a "snippet"; only blueprints can be hosted.',
    );
});

it('never puts the definition object into browser-visible state', function (): void {
    $component = Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects']);
    $snapshot = json_encode($component->snapshot, JSON_THROW_ON_ERROR);

    expect($snapshot)->not->toContain('Gcego\\\\Blueprints')
        ->and($snapshot)->not->toContain('Mathematics')
        ->and(array_keys($component->snapshot['data']))->toBe(['blueprint', 'state']);
});

it('authorizes again on every request', function (): void {
    $component = Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects']);
    Gate::define('gcego-runtime.render', fn (?object $user, string $identity): bool => false);

    $component->set('state.subjects:search.query', 'x')->assertForbidden();
});
