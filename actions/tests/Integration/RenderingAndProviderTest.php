<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Actions\ActionManager;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Actions\Authorization\SecurityActionAuthorizer;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Contracts\ActionAuthorizer;
use Gcego\Actions\Delete;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionExecutionEngine;
use Gcego\Actions\Handlers\DeleteRecord;
use Gcego\Actions\Input\RulesInputResolver;
use Gcego\Actions\Livewire\ActionHost;
use Gcego\Actions\Rendering\ActionRenderer;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Actions\Tests\Fixtures\Blueprints;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\ChromaManager;
use Gcego\Components\Property;
use Gcego\Core\Gcego;
use Gcego\Data\Source;
use Gcego\Luminaries\Facades\Luminaries;
use Gcego\Registry\Registry;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Runtime;
use Gcego\Security\Requirements\PublicAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('renders actions of registered blueprints through the runtime', function (): void {
    actAs($this->world['admin']);

    $html = app(Runtime::class)->render('blueprint:subjects', metadata: ['record' => $this->world['subjects']['algebra']->id])->toHtml();

    expect($html)->toContain('data-gcego-actions="blueprint:subjects"')
        ->and($html)->toContain('data-gcego-action="delete"')
        ->and($html)->toContain('wire:click="run(\'edit\')"')
        ->and(app(RendererRegistry::class)->bindingFor(Action::class)?->rendererClass())->toBe(ActionRenderer::class);
});

it('renders nothing for blueprints without a registry identity', function (): void {
    actAs($this->world['admin']);

    expect(app(Runtime::class)->render(Blueprints::subjects())->toHtml())->not->toContain('data-gcego-action=');
});

it('styles actions through gcego/chroma, with intent and Tailwind overrides', function (): void {
    $styling = app(ChromaManager::class);
    $delete = new Delete;
    $styled = app(ActionResolver::class)->resolve('blueprint:subjects', 'styled')->action;

    expect($styling->classes($delete, 'destructive'))->toContain('bg-gcego-danger')
        ->and($styling->classes($delete, 'root'))->toContain('focus-visible:outline-gcego-ring')
        ->and($styling->merge($styling->classes($styled, 'secondary'), $styling->classes($styled, 'root')))->toContain('rounded-full')
        ->and($styling->merge($styling->classes($styled, 'secondary'), $styling->classes($styled, 'root')))->toContain('bg-white')
        ->and($styling->merge($styling->classes($styled, 'secondary'), $styling->classes($styled, 'root')))->not->toContain('bg-gcego-surface ');

    actAs($this->world['admin']);
    expect(Livewire::test(ActionHost::class, ['definition' => 'subjects', 'record' => $this->world['subjects']['algebra']->id, 'placement' => 'row'])->html())->toContain('bg-gcego-danger');
});

it('renders action icons through semantic aliases that applications can override', function (): void {
    actAs($this->world['admin']);
    $render = fn (): string => Livewire::test(ActionHost::class, ['definition' => 'subjects', 'record' => $this->world['subjects']['algebra']->id, 'placement' => 'row'])->html();
    $default = $render();
    Luminaries::alias('actions.edit', 'lucide:pencil', override: true);

    expect($default)->toContain('data-gcego-icon="heroicon:pencil-square"')
        ->toContain('aria-hidden="true"')
        ->and($render())->toContain('data-gcego-icon="lucide:pencil"');
});

it('registers services, the Livewire host and the package', function (): void {
    expect(app(ActionManager::class))->toBe(app(ActionManager::class))
        ->and(app(ActionPlacement::class))->toBe(app(ActionPlacement::class))
        ->and(app(ActionAuthorizer::class))->toBeInstanceOf(SecurityActionAuthorizer::class)
        ->and(app(Gcego::class)->bootResult()?->packages)->toContain('gcego/actions')
        ->and(ServiceProvider::pathsToPublish(ActionsServiceProvider::class, 'gcego-actions-config'))->toHaveCount(1)
        ->and(Livewire::test('gcego-actions', ['definition' => 'subjects'])->instance())->toBeInstanceOf(ActionHost::class);
});

it('scopes the engine per request and boots without database access', function (): void {
    $engine = app(ActionExecutionEngine::class);
    app()->forgetScopedInstances();

    expect(app(ActionExecutionEngine::class))->not->toBe($engine);

    $this->refreshApplication();

    expect(DB::getConnections())->toBe([]);
});

it('orders actions by placement and order', function (): void {
    expect(array_map(fn ($action) => $action->name(), app(ActionManager::class)->actions('blueprint:subjects', ActionPlacement::Row)))
        ->toBe(['view', 'edit', 'restore', 'delete', 'force-delete'])
        ->and(array_map(fn ($action) => $action->name(), app(ActionManager::class)->actions('blueprint:subjects', ActionPlacement::Bulk)))
        ->toBe(['bulk-publish', 'bulk-delete']);
});

it('detects duplicate action names', function (): void {
    app(Registry::class)->register(new Blueprint('twice', new PublicAccess, new Delete, new Delete('Again')));

    expect(fn () => app(ActionManager::class)->actions('blueprint:twice'))->toThrow(InvalidActionException::class, 'two actions share this name')
        ->and(app(ActionManager::class)->inspect('blueprint:twice')->isHealthy())->toBeFalse();
});

it('inspects actions without executing them', function (): void {
    $queries = queriesDuring(function () use (&$report): void {
        $report = app(ActionManager::class)->inspect('blueprint:subjects');
    });
    $delete = collect($report->actions)->firstWhere('identity', 'blueprint:subjects/action:delete');
    $errors = array_map(fn ($diagnostic) => $diagnostic->message, $report->errors());

    expect($queries)->toBe([])
        ->and($delete)->toMatchArray(['type' => Delete::class, 'ability' => 'delete', 'confirmation' => true, 'transaction' => true, 'record' => true, 'renderer' => true, 'handler' => DeleteRecord::class])
        ->and(collect($report->actions)->firstWhere('identity', 'blueprint:subjects/action:create')['input'] ?? null)->toBe(RulesInputResolver::class)
        ->and($errors)->toBe(['blueprint:subjects/action:ungoverned declares no Ability and no requirement: it is denied to everyone.']);
});

it('resolves actions quickly for large blueprints', function (): void {
    $actions = array_map(fn (int $i): Action => new Action('action-'.$i, new Property(Action::Ability, 'viewAny'), new Handler(fn () => true)), range(1, 200));
    app(Registry::class)->register(new Blueprint('large', new PublicAccess, new Source(Subject::class), new ActionGroup('all', 'All', ...$actions)));
    $start = hrtime(true);

    foreach (range(1, 50) as $i) {
        app(ActionManager::class)->resolve('blueprint:large', 'action-'.(($i * 7) % 200 + 1));
    }

    $elapsed = (hrtime(true) - $start) / 1e6;
    fwrite(STDERR, sprintf("\n[measured] 50 resolutions in a 200-action blueprint: %.1f ms (%.2f ms each)\n", $elapsed, $elapsed / 50));

    expect($elapsed)->toBeLessThan(5000.0);
});

it('measures authorization overhead of a full execution', function (): void {
    actAs($this->world['editor']);
    $start = hrtime(true);

    foreach (range(1, 50) as $i) {
        act('view', ['record' => $this->world['subjects']['algebra']->id]);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;
    fwrite(STDERR, sprintf("\n[measured] 50 View executions (resolve, scoped lookup, authorization): %.1f ms (%.2f ms each)\n", $elapsed, $elapsed / 50));

    expect($elapsed)->toBeLessThan(5000.0);
});

it('renders action hosts nested inside the runtime Livewire blueprint host', function (): void {
    actAs($this->world['admin']);

    $html = Livewire::test(BlueprintHost::class, ['blueprint' => 'subjects'])->assertOk()->html();

    expect($html)->toContain('data-gcego-actions="blueprint:subjects"')
        ->and($html)->toContain('data-gcego-action="create"');
});
