<?php

declare(strict_types=1);

namespace Gcego\Testing;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Layouts\LayoutComponent;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Tables\Table;
use Gcego\Testing\Accessibility\BrowserAccessibility;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Compatibility\PackageCompatibility;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Diagnostics\AssertionFailureFormatter;
use Gcego\Testing\Diagnostics\Redactor;
use Gcego\Testing\Diagnostics\TestingInspector;
use Gcego\Testing\Environment\TestingEnvironment;
use Gcego\Testing\Environment\TestStateResetter;
use Gcego\Testing\Exceptions\MissingPackageException;
use Gcego\Testing\Fixtures\FixtureManager;
use Gcego\Testing\Livewire\LivewireRequests;
use Gcego\Testing\Livewire\LivewireTestAdapter;
use Gcego\Testing\Performance\PerformanceRecorder;
use Gcego\Testing\Performance\PerformanceTestable;
use Gcego\Testing\Performance\QueryCounter;
use Gcego\Testing\Testables\AccessibilityTestable;
use Gcego\Testing\Testables\ActionTestable;
use Gcego\Testing\Testables\AnalyticsTestable;
use Gcego\Testing\Testables\BlueprintTestable;
use Gcego\Testing\Testables\ComponentTestable;
use Gcego\Testing\Testables\ContractTestable;
use Gcego\Testing\Testables\DataTestable;
use Gcego\Testing\Testables\FormTestable;
use Gcego\Testing\Testables\HtmlTestable;
use Gcego\Testing\Testables\InboxTestable;
use Gcego\Testing\Testables\InfolistTestable;
use Gcego\Testing\Testables\LayoutTestable;
use Gcego\Testing\Testables\MediaTestable;
use Gcego\Testing\Testables\ModuleTestable;
use Gcego\Testing\Testables\NavigationTestable;
use Gcego\Testing\Testables\NotificationTestable;
use Gcego\Testing\Testables\PageTestable;
use Gcego\Testing\Testables\PanelTestable;
use Gcego\Testing\Testables\RegistryTestable;
use Gcego\Testing\Testables\RuntimeTestable;
use Gcego\Testing\Testables\SecurityTestable;
use Gcego\Testing\Testables\StylingTestable;
use Gcego\Testing\Testables\TableTestable;
use Gcego\Testing\Testables\TenantTestable;
use Gcego\Testing\Testables\WidgetTestable;
use Gcego\Testing\Testables\WorkflowDefinitionTestable;
use Gcego\Testing\Testables\WorkflowTestable;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Workflow;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates the testables, sharing one verifier (failure formatting and
 * redaction), one test environment (acting user and tenant) and one
 * fixture manager. Each testable delegates to the production services of
 * its package; this class holds no testing logic of its own.
 */
final class TestingManager
{
    private readonly Verifier $verifier;

    private ?TestEnvironment $environment = null;

    public function __construct(private readonly Container $container, private readonly TestingConfig $config = new TestingConfig)
    {
        $this->verifier = new Verifier(new AssertionFailureFormatter(new Redactor($config->redact), $config->verboseFailures));
    }

    public function verifier(): Verifier
    {
        return $this->verifier;
    }

    public function config(): TestingConfig
    {
        return $this->config;
    }

    public function environment(): TestEnvironment
    {
        return $this->environment ??= $this->container->bound(TestEnvironment::class) ? $this->container->make(TestEnvironment::class) : new TestingEnvironment($this->container);
    }

    public function fixtures(): FixtureManager
    {
        if (! $this->container->bound(FixtureManager::class)) {
            $this->container->instance(FixtureManager::class, new FixtureManager($this->container, $this->config->fixturesDirectory, $this->config->userProvider, $this->config->tenantProvider));
        }

        return $this->container->make(FixtureManager::class);
    }

    public function resetter(): TestStateResetter
    {
        return $this->container->bound(TestStateResetter::class) ? $this->container->make(TestStateResetter::class) : new TestStateResetter($this->container, $this->config->isolation);
    }

    public function component(ComponentInterface $component): ComponentTestable
    {
        return new ComponentTestable($this->verifier, $component);
    }

    public function blueprint(BlueprintInterface $blueprint): BlueprintTestable
    {
        $this->needs('Gcego\Blueprints\Blueprint', 'gcego/blueprints', 'GcegoTest::blueprint()');

        return new BlueprintTestable($this->verifier, $blueprint);
    }

    public function registry(): RegistryTestable
    {
        $this->needs('Gcego\Registry\Registry', 'gcego/registry', 'GcegoTest::registry()');

        return new RegistryTestable($this->verifier, $this->container);
    }

    public function runtime(): RuntimeTestable
    {
        $this->needs('Gcego\Rendering\RenderEngine', 'gcego/rendering', 'GcegoTest::runtime()');

        return new RuntimeTestable($this->verifier, $this->container);
    }

    public function styling(ComponentInterface $component, ?string $theme = null): StylingTestable
    {
        $this->needs('Gcego\Chroma\ChromaManager', 'gcego/chroma', 'GcegoTest::styling()');

        return new StylingTestable($this->verifier, $this->container, $component, $theme);
    }

    public function data(Source|SourceDefinition $source): DataTestable
    {
        $this->needs('Gcego\Data\DataManager', 'gcego/data', 'GcegoTest::data()');

        return new DataTestable($this->verifier, $this->container, $this->environment(), $source);
    }

    public function security(): SecurityTestable
    {
        $this->needs('Gcego\Security\SecurityManager', 'gcego/security', 'GcegoTest::security()');

        return new SecurityTestable($this->verifier, $this->container, $this->environment());
    }

    public function tenant(string|int|Fixtures\TenantFixture $tenant): TenantTestable
    {
        $this->needs('Gcego\Security\SecurityManager', 'gcego/security', 'GcegoTest::tenant()');

        return new TenantTestable($this->verifier, $this->container, $this->environment(), $tenant);
    }

    public function action(string $definition, string $action): ActionTestable
    {
        $this->needs('Gcego\Actions\ActionManager', 'gcego/actions', 'GcegoTest::action()');

        return new ActionTestable($this->verifier, $this->container, $this->environment(), $definition, $action);
    }

    /**
     * @param  class-string<Model>|null  $model
     */
    public function form(Form $form, ?string $model = null): FormTestable
    {
        $this->needs('Gcego\Forms\FormManager', 'gcego/forms', 'GcegoTest::form()');

        return new FormTestable($this->verifier, $this->container, $this->environment(), $form, $model);
    }

    public function table(string|Table $table): TableTestable
    {
        $this->needs('Gcego\Tables\TableManager', 'gcego/tables', 'GcegoTest::table()');

        return new TableTestable($this->verifier, $this->container, $this->environment(), $table);
    }

    public function infolist(string $definition): InfolistTestable
    {
        $this->needs('Gcego\Infolists\InfolistManager', 'gcego/infolists', 'GcegoTest::infolist()');

        return new InfolistTestable($this->verifier, $this->container, $this->environment(), $definition);
    }

    public function widget(string $identity): WidgetTestable
    {
        $this->needs('Gcego\Widgets\WidgetManager', 'gcego/widgets', 'GcegoTest::widget()');

        return new WidgetTestable($this->verifier, $this->container, $this->environment(), $identity);
    }

    public function layout(string|LayoutComponent $layout): LayoutTestable
    {
        $this->needs('Gcego\Layouts\LayoutManager', 'gcego/layouts', 'GcegoTest::layout()');

        return new LayoutTestable($this->verifier, $this->container, $this->environment(), $layout);
    }

    public function navigation(string $navigation): NavigationTestable
    {
        $this->needs('Gcego\Navigation\NavigationManager', 'gcego/navigation', 'GcegoTest::navigation()');

        return new NavigationTestable($this->verifier, $this->container, $this->environment(), $navigation);
    }

    public function page(string $page): PageTestable
    {
        $this->needs('Gcego\Pages\PageManager', 'gcego/pages', 'GcegoTest::page()');

        return new PageTestable($this->verifier, $this->container, $this->environment(), $page);
    }

    public function module(string $module): ModuleTestable
    {
        $this->needs('Gcego\Modules\ModuleManager', 'gcego/modules', 'GcegoTest::module()');

        return new ModuleTestable($this->verifier, $this->container, $module);
    }

    public function panel(string $panel): PanelTestable
    {
        $this->needs('Gcego\Panels\PanelManager', 'gcego/panels', 'GcegoTest::panel()');

        return new PanelTestable($this->verifier, $this->container, $this->environment(), $panel);
    }

    public function notification(DispatchResult $result): NotificationTestable
    {
        return new NotificationTestable($this->verifier, $result);
    }

    public function inbox(Model $user, string|int|null $tenant = null, ?string $panel = null): InboxTestable
    {
        $this->needs('Gcego\Notifications\NotificationManager', 'gcego/notifications', 'GcegoTest::inbox()');

        return new InboxTestable($this->verifier, $this->container, $user, $tenant === null ? null : (string) $tenant, $panel);
    }

    public function media(): MediaTestable
    {
        $this->needs('Gcego\Media\MediaManager', 'gcego/media', 'GcegoTest::media()');

        return new MediaTestable($this->verifier, $this->container, $this->environment());
    }

    public function workflow(WorkflowInstance|Workflow $workflow): WorkflowTestable|WorkflowDefinitionTestable
    {
        return $workflow instanceof Workflow
            ? new WorkflowDefinitionTestable($this->verifier, $workflow)
            : new WorkflowTestable($this->verifier, $this->container, $workflow);
    }

    public function analytics(ComponentInterface $definition): AnalyticsTestable
    {
        return new AnalyticsTestable($this->verifier, $definition);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function livewire(string $component, array $parameters = []): LivewireTestAdapter
    {
        $this->needs('Livewire\Livewire', 'livewire/livewire', 'GcegoTest::livewire()');

        return new LivewireTestAdapter($this->verifier, $this->environment(), $component, $parameters);
    }

    public function livewireRequests(): LivewireRequests
    {
        $this->needs('Livewire\Livewire', 'livewire/livewire', 'GcegoTest::livewireRequests()');

        return new LivewireRequests($this->container);
    }

    public function html(string $html): HtmlTestable
    {
        return new HtmlTestable($this->verifier, $html, 'markup', $this->browser());
    }

    public function accessibility(string $html): AccessibilityTestable
    {
        return new AccessibilityTestable($this->verifier, $html, $this->browser());
    }

    public function performance(): PerformanceTestable
    {
        return new PerformanceTestable($this->verifier, new PerformanceRecorder(new QueryCounter($this->container->make(Dispatcher::class))), $this->config->repeatedQueries);
    }

    public function contracts(): ContractTestable
    {
        return new ContractTestable($this->verifier);
    }

    public function inspector(): TestingInspector
    {
        return new TestingInspector($this->container, $this->config);
    }

    public function compatibility(): PackageCompatibility
    {
        return new PackageCompatibility($this->container instanceof Application ? $this->container : null);
    }

    public function browser(): BrowserAccessibility
    {
        return new BrowserAccessibility($this->config->node, $this->config->nodeModules, $this->container instanceof Application ? $this->container->basePath() : null);
    }

    private function needs(string $class, string $package, string $feature): void
    {
        if (! class_exists($class)) {
            throw MissingPackageException::for($package, $feature);
        }
    }
}
