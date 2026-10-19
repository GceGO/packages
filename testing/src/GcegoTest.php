<?php

declare(strict_types=1);

namespace Gcego\Testing;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Layouts\LayoutComponent;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Tables\Table;
use Gcego\Testing\Compatibility\PackageCompatibility;
use Gcego\Testing\Diagnostics\TestingInspector;
use Gcego\Testing\Fixtures\FixtureManager;
use Gcego\Testing\Fixtures\TenantFixture;
use Gcego\Testing\Livewire\LivewireRequests;
use Gcego\Testing\Livewire\LivewireTestAdapter;
use Gcego\Testing\Performance\PerformanceTestable;
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
use InvalidArgumentException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * The entry point of the toolkit:
 *
 *   GcegoTest::component(new TextInput('name', 'Name'))->assertValid()->assertName('name');
 *   GcegoTest::table('subjects')->assertColumnSearchable('name');
 *   GcegoTest::action('subjects', 'delete')->actingAs($admin, 1)->record($subject)->confirmed()->execute()->assertSuccessful();
 *
 * Every method returns a testable from the TestingManager bound in the
 * application container (or one built with the default configuration
 * outside Laravel, for pure component and blueprint tests).
 */
final class GcegoTest
{
    public static function manager(): TestingManager
    {
        $container = Container::getInstance();

        if ($container->bound(TestingManager::class)) {
            return $container->make(TestingManager::class);
        }

        $config = $container->bound('config') ? $container->make('config')->get('gcego-testing', []) : [];

        return new TestingManager($container, TestingConfig::fromArray(is_array($config) ? $config : []));
    }

    public static function component(ComponentInterface|Buildable $component): ComponentTestable
    {
        return self::manager()->component($component instanceof Buildable ? $component->build() : $component);
    }

    public static function blueprint(BlueprintInterface $blueprint): BlueprintTestable
    {
        return self::manager()->blueprint($blueprint);
    }

    public static function registry(): RegistryTestable
    {
        return self::manager()->registry();
    }

    public static function runtime(): RuntimeTestable
    {
        return self::manager()->runtime();
    }

    public static function styling(ComponentInterface $component, ?string $theme = null): StylingTestable
    {
        return self::manager()->styling($component, $theme);
    }

    public static function data(Source|SourceDefinition $source): DataTestable
    {
        return self::manager()->data($source);
    }

    public static function security(): SecurityTestable
    {
        return self::manager()->security();
    }

    public static function tenant(string|int|TenantFixture $tenant): TenantTestable
    {
        return self::manager()->tenant($tenant);
    }

    /**
     * @param  string  $definition  The registered blueprint or page ("subjects", "blueprint:subjects", "page:subjects-edit").
     */
    public static function action(string $definition, string $action): ActionTestable
    {
        return self::manager()->action($definition, $action);
    }

    /**
     * @param  class-string<Model>|null  $model
     */
    public static function form(Form|Buildable $form, ?string $model = null): FormTestable
    {
        $form = $form instanceof Buildable ? $form->build() : $form;

        if (! $form instanceof Form) {
            throw new InvalidArgumentException('GcegoTest::form() needs a Form or a Form builder.');
        }

        return self::manager()->form($form, $model);
    }

    public static function table(string|Table|Buildable $table): TableTestable
    {
        $table = $table instanceof Buildable ? $table->build() : $table;

        if (! is_string($table) && ! $table instanceof Table) {
            throw new InvalidArgumentException('GcegoTest::table() needs a Table, a Table builder or a registered table name.');
        }

        return self::manager()->table($table);
    }

    public static function infolist(string $definition): InfolistTestable
    {
        return self::manager()->infolist($definition);
    }

    public static function widget(string $identity): WidgetTestable
    {
        return self::manager()->widget($identity);
    }

    public static function layout(string|LayoutComponent $layout): LayoutTestable
    {
        return self::manager()->layout($layout);
    }

    public static function navigation(string $navigation): NavigationTestable
    {
        return self::manager()->navigation($navigation);
    }

    public static function page(string $page): PageTestable
    {
        return self::manager()->page($page);
    }

    public static function module(string $module): ModuleTestable
    {
        return self::manager()->module($module);
    }

    public static function panel(string $panel): PanelTestable
    {
        return self::manager()->panel($panel);
    }

    public static function notification(DispatchResult $result): NotificationTestable
    {
        return self::manager()->notification($result);
    }

    public static function inbox(Model $user, string|int|null $tenant = null, ?string $panel = null): InboxTestable
    {
        return self::manager()->inbox($user, $tenant, $panel);
    }

    public static function media(): MediaTestable
    {
        return self::manager()->media();
    }

    public static function workflow(WorkflowInstance|Workflow $workflow): WorkflowTestable|WorkflowDefinitionTestable
    {
        return self::manager()->workflow($workflow);
    }

    public static function analytics(ComponentInterface $definition): AnalyticsTestable
    {
        return self::manager()->analytics($definition);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function livewire(string $component, array $parameters = []): LivewireTestAdapter
    {
        return self::manager()->livewire($component, $parameters);
    }

    /**
     * Real Livewire update requests through the HTTP kernel.
     */
    public static function livewireRequests(): LivewireRequests
    {
        return self::manager()->livewireRequests();
    }

    public static function html(string $html): HtmlTestable
    {
        return self::manager()->html($html);
    }

    public static function accessibility(string $html): AccessibilityTestable
    {
        return self::manager()->accessibility($html);
    }

    public static function performance(): PerformanceTestable
    {
        return self::manager()->performance();
    }

    public static function contracts(): ContractTestable
    {
        return self::manager()->contracts();
    }

    public static function fixtures(): FixtureManager
    {
        return self::manager()->fixtures();
    }

    public static function inspector(): TestingInspector
    {
        return self::manager()->inspector();
    }

    public static function compatibility(): PackageCompatibility
    {
        return self::manager()->compatibility();
    }

    /**
     * Clears the user, tenant, panel context, Livewire state, registry
     * resolutions and fixtures (see TestStateResetter).
     */
    public static function reset(): void
    {
        self::manager()->resetter()->reset();
    }
}
