<?php

declare(strict_types=1);

namespace Gcego\Workflows;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Notifications\Definitions\NotificationCategory;
use Gcego\Notifications\NotificationManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Approvals\ApprovalCoordinator;
use Gcego\Workflows\Automation\AutomationDispatcher;
use Gcego\Workflows\Automation\AutomationRunner;
use Gcego\Workflows\Console\InspectWorkflowsCommand;
use Gcego\Workflows\Console\ProcessDeadlinesCommand;
use Gcego\Workflows\Diagnostics\WorkflowInspector;
use Gcego\Workflows\Engine\CommandRunner;
use Gcego\Workflows\Engine\StateEntry;
use Gcego\Workflows\Engine\TransitionExecutor;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Integration\PanelWorkflowsExtension;
use Gcego\Workflows\Integration\WorkflowNotifier;
use Gcego\Workflows\Livewire\WorkflowApproval;
use Gcego\Workflows\Livewire\WorkflowInbox;
use Gcego\Workflows\Livewire\WorkflowPanel;
use Gcego\Workflows\Livewire\WorkflowTimeline;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Registry\WorkflowKind;
use Gcego\Workflows\Scheduling\DeadlineProcessor;
use Gcego\Workflows\Support\SubjectState;
use Gcego\Workflows\Support\WorkflowLabels;
use Gcego\Workflows\Support\WorkflowStyles;
use Gcego\Workflows\Tasks\Inbox;
use Gcego\Workflows\Tasks\TaskAssigner;
use Gcego\Workflows\Tasks\TaskFactory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/workflows; also the "gcego/workflows" GCEGO package.
 *
 * register(): configuration, services and the "workflow" registry kind
 * with its discovery root (no queries, no definitions loaded).
 * boot(): views, translations, migrations, Livewire components
 * (gcego-workflow-inbox, -panel, -approval, -timeline), styling slots, the
 * notification category, the panel extension, the deadline schedule and
 * the console commands.
 */
final class WorkflowsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-workflows';

    /** Styling slots of workflow views (ChromaManager::override(Workflow::class, ...)). */
    public const array Slots = [
        'inbox' => 'The inbox page.', 'card' => 'The inbox list.', 'item' => 'An inbox item.', 'empty' => 'Empty states.',
        'panel' => 'The workflow panel.', 'actions' => 'Transition buttons.', 'approval' => 'An approval or task card.', 'timeline' => 'The history.',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-workflows.php', self::Name);

        $this->app->singleton(WorkflowsConfig::class, static function (Application $app): WorkflowsConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return WorkflowsConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([WorkflowDefinitions::class, HistoryRecorder::class, WorkflowInspector::class, WorkflowStyles::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request (or queue job): they read the current user, tenant and authorization state.
        foreach ([WorkflowAuthorizer::class, CommandRunner::class, TaskAssigner::class, TaskFactory::class, WorkflowNotifier::class, ApprovalCoordinator::class, AutomationDispatcher::class, AutomationRunner::class, StateEntry::class, TransitionExecutor::class, WorkflowEngine::class, DeadlineProcessor::class, WorkflowRepository::class, Inbox::class, WorkflowLabels::class, SubjectState::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new WorkflowKind));

        // Configuration is final by the time this is first resolved (Testbench sets it after register()).
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->publishes([$root.'/config/gcego-workflows.php' => $this->app->configPath('gcego-workflows.php')], 'gcego-workflows-config');
        $this->publishes([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-workflows-migrations');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-workflows')], 'gcego-workflows-views');

        Livewire::component('gcego-workflow-inbox', WorkflowInbox::class);
        Livewire::component('gcego-workflow-panel', WorkflowPanel::class);
        Livewire::component('gcego-workflow-approval', WorkflowApproval::class);
        Livewire::component('gcego-workflow-timeline', WorkflowTimeline::class);

        $slots = [];

        foreach (self::Slots as $slot => $description) {
            $slots[] = new SlotDefinition($slot, description: $description);
        }

        $this->app->make(ChromaManager::class)->defineSlots(Workflow::class, $slots, inherit: true);

        if ($this->app->bound(NotificationManager::class)) {
            $this->app->make(NotificationManager::class)->category(new NotificationCategory(WorkflowNotifier::Category, 'gcego-workflows::messages.category'));
        }

        if ($this->app->bound(PanelManager::class)) {
            $this->app->make(PanelManager::class)->extend(PanelWorkflowsExtension::class);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $config = $this->app->make(WorkflowsConfig::class);

            if ($config->deadlines && $config->schedule) {
                $schedule->command(ProcessDeadlinesCommand::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessDeadlinesCommand::class, InspectWorkflowsCommand::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/workflows',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/widgets', 'gcego/navigation', 'gcego/panels', 'gcego/notifications'],
            capabilities: ['workflows', 'workflows.approvals', 'workflows.tasks', 'workflows.deadlines', 'workflows.automation', 'workflows.history', 'workflows.inbox'],
            description: 'Declarative workflows: guarded transitions, approvals, human tasks, deadlines, automation and audited history.',
        );
    }

    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);
        $paths = $config->get(self::Name.'.discovery.paths', []);
        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach (is_array($paths) ? $paths : [] as $path) {
            foreach ($roots as $root) {
                if (! is_string($path) || (is_array($root) && ($root['kind'] ?? null) === WorkflowKind::Id && ($root['path'] ?? null) === $path)) {
                    continue 2;
                }
            }

            $roots[] = ['path' => $path, 'kind' => WorkflowKind::Id, 'entrypoint' => 'file'];
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }
}
