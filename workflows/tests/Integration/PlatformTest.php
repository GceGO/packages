<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Data\DataManager;
use Gcego\Navigation\Resolution\NavigationContext;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Diagnostics\WorkflowInspector;
use Gcego\Workflows\Integration\Navigation\InboxBadge;
use Gcego\Workflows\Integration\Widgets\PendingWorkCount;
use Gcego\Workflows\Integration\Widgets\WorkflowStateCounts;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Illuminate\Http\Request;

it('discovers workflows from the configured folders as the "workflow" registry kind', function (): void {
    $registry = app(RegistryInterface::class);
    $descriptor = collect($registry->descriptors('workflow'))->first(fn ($descriptor) => $descriptor->name() === 'registration');

    expect(app(WorkflowDefinitions::class)->names())->toBe(['leave', 'onboarding', 'registration'])
        ->and($registry->get('workflow:leave'))->toBeInstanceOf(Workflow::class)
        ->and($descriptor?->metadata['version'] ?? null)->toBe(2)
        ->and($descriptor?->metadata['approvals'] ?? null)->toBe(1);
});

it('refuses to register invalid workflows', function (): void {
    app(RegistryInterface::class)->register(new Workflow('broken', new State('draft'), new Transition('go', 'draft', 'nowhere')));
})->throws(InvalidDefinitionException::class, 'goes to the unknown state "nowhere"');

it('inspects definitions, running instances and configuration', function (): void {
    $world = world();
    inReview($world);

    $report = app(WorkflowInspector::class)->report();
    $registration = collect($report['workflows'])->firstWhere('name', 'registration');

    expect($registration['version'] ?? null)->toBe(2)->and($registration['problems'] ?? null)->toBe([])
        ->and($report['runtime']['instances'] ?? [])->toBe(['active' => 1])->and($report['runtime']['open_tasks'] ?? null)->toBe(3)
        ->and($report['config']['enforce_tenant_scope'] ?? null)->toBeTrue();

    $this->artisan('gcego:workflows:inspect')->expectsOutputToContain('registration')->assertSuccessful();
    $this->artisan('gcego:workflows:inspect', ['--json' => true, '--definitions' => true])->expectsOutputToContain('"leave-approval @ requested"')->assertSuccessful();
});

it('shows workflow states in module tables and infolists, eager loading instances', function (): void {
    $world = world();
    actAs($world['editor']);
    engine()->start('registration', $world['alpha'], $world['editor']);
    $instance = engine()->start('registration', $world['beta'], $world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);

    $page = html($this->actingAs($world['editor'])->withSession(tenantSession())->get('/desk/applications')->assertOk());

    expect($page)->toContain('Draft')->toContain('Submitted');

    $view = html($this->actingAs($world['editor'])->withSession(tenantSession())->get('/desk/applications/'.$world['beta']->id)->assertOk());
    expect($view)->toContain('Submitted');
});

it('starts workflows and runs transitions from gcego/actions, re-authorized by the engine', function (): void {
    $world = world();
    $world['beta']->forceFill(['documents' => false])->save();
    actAs($world['editor']);
    onWorkflowPage('/desk/applications/'.$world['alpha']->id);
    $manager = app(ActionManager::class);

    $started = $manager->execute(new ActionRequest('page:admissions-applications-view', 'start-registration', $world['alpha']->id));
    $submitted = $manager->execute(new ActionRequest('page:admissions-applications-view', 'submit-registration', $world['alpha']->id));

    expect($started->status)->toBe(ActionStatus::Success)->and($submitted->status)->toBe(ActionStatus::Success)
        ->and(engine()->running('registration', $world['alpha'])?->state)->toBe('submitted');

    $manager->execute(new ActionRequest('page:admissions-applications-view', 'start-registration', $world['beta']->id));
    $refused = $manager->execute(new ActionRequest('page:admissions-applications-view', 'submit-registration', $world['beta']->id));

    expect($refused->status)->toBe(ActionStatus::ValidationFailed)->and($refused->errors['workflow'][0] ?? null)->toBe('Upload the registration documents first.');
});

it('passes a validated action comment to the transition', function (): void {
    $world = world();
    actAs($world['editor']);
    engine()->start('registration', $world['alpha'], $world['editor']);
    onWorkflowPage('/desk/applications/'.$world['alpha']->id);
    $manager = app(ActionManager::class);

    $missing = $manager->execute(new ActionRequest('page:admissions-applications-view', 'withdraw-registration', $world['alpha']->id, [], ['comment' => '']));
    $withdrawn = $manager->execute(new ActionRequest('page:admissions-applications-view', 'withdraw-registration', $world['alpha']->id, [], ['comment' => 'Merged with another application.']));
    $instance = WorkflowInstance::query()->firstOrFail();

    expect($missing->status)->toBe(ActionStatus::ValidationFailed)->and($missing->errors)->toHaveKey('comment')
        ->and($withdrawn->status)->toBe(ActionStatus::Success)->and($instance->state)->toBe('expired')
        ->and($instance->events()->where('type', 'workflow.transitioned')->first()?->metadata['comment'] ?? null)->toBe('Merged with another application.');
});

it('provides widget data: state counts in the tenant and the viewer\'s pending work', function (): void {
    $world = world();
    inReview($world);
    actAs($world['editor']);
    engine()->start('registration', $world['beta'], $world['editor']);
    actAs($world['south'], 2);
    engine()->start('leave', $world['southern'], SystemActor::named('importer'), tenant: '2');

    $provider = new class extends WorkflowStateCounts
    {
        protected function workflow(): string
        {
            return 'registration';
        }
    };
    $context = static fn ($user, int $tenant): WidgetDataContext => new WidgetDataContext('w', 'p', $user, new Tenant('institution', $tenant), null, null, 1, 'en', 'UTC', app(DataManager::class));

    actAs($world['reviewer1']);
    $counts = $provider->resolve($context($world['reviewer1'], 1));
    expect(array_combine($counts->labels, $counts->datasets[0]->data))->toMatchArray(['Draft' => 1, 'Submitted' => 0, 'Under review' => 1])
        ->and((new PendingWorkCount)->resolve($context($world['reviewer1'], 1))->value)->toBe(1);
});

it('counts the inbox for navigation badges', function (): void {
    $world = world();
    inReview($world);
    actAs($world['reviewer2']);

    $badge = (new InboxBadge)->badge(new NavigationContext('main', $world['reviewer2'], new Tenant('institution', 1), null, [], '/', [], 'localhost', 'x', app(SecurityManager::class), app(DataManager::class)));

    expect($badge?->text)->toBe('1');
});

it('adds the inbox and instance pages to panels, behind the panel and the instance authorization', function (): void {
    $world = world();
    $instance = inReview($world);

    $inbox = html($this->actingAs($world['reviewer2'])->withSession(tenantSession())->get('/desk/workflows')->assertOk());
    expect($inbox)->toContain('Workflow inbox')->toContain('Academic review')->toContain('/desk/workflows/'.$instance->ulid);

    $page = html($this->actingAs($world['reviewer2'])->withSession(tenantSession())->get('/desk/workflows/'.$instance->ulid)->assertOk());
    expect($page)->toContain('Institution Registration')->toContain('Under review')->toContain('History');

    $this->actingAs($world['visitor'])->withSession(tenantSession())->get('/desk/workflows/'.$instance->ulid)->assertNotFound();
    app('auth')->forgetGuards();
    $this->actingAs($world['south'])->withSession(tenantSession(2))->get('/desk/workflows/'.$instance->ulid)->assertNotFound();
    $this->actingAs($world['reviewer2'])->withSession(tenantSession())->get('/desk/workflows/not-a-ulid')->assertNotFound();
});

/**
 * Make $uri the current request (route matched and bound), as during a page request.
 */
function onWorkflowPage(string $uri): void
{
    $request = Request::create($uri, 'GET');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(static fn () => $route);
    app()->instance('request', $request);
}
