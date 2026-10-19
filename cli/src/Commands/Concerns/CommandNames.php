<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Concerns;

use Gcego\Cli\Commands;
use Illuminate\Console\Command;

/**
 * Every command gcego/cli owns. gcego:install (gcego/studio) and
 * gcego:registry-cache / gcego:registry-clear (gcego/registry) are not
 * redefined: the CLI documents and delegates to them.
 */
final class CommandNames
{
    public const string Version = '0.1.0';

    /** @var array<string, class-string<Command>> */
    public const array Commands = [
        'gcego:about' => Commands\AboutCommand::class,
        'gcego:doctor' => Commands\DoctorCommand::class,
        'gcego:list' => Commands\ListCommand::class,
        'gcego:registry' => Commands\RegistryCommand::class,
        'gcego:validate' => Commands\ValidateCommand::class,
        'gcego:inspect' => Commands\InspectCommand::class,
        'gcego:routes' => Commands\RoutesCommand::class,
        'gcego:assets' => Commands\AssetsCommand::class,
        'gcego:packages' => Commands\PackagesCommand::class,
        'gcego:cache-status' => Commands\CacheStatusCommand::class,
        'gcego:make' => Commands\MakeCommand::class,
        'gcego:make-blueprint' => Commands\Make\MakeBlueprintCommand::class,
        'gcego:make-module' => Commands\Make\MakeModuleCommand::class,
        'gcego:make-resource' => Commands\Make\MakeResourceCommand::class,
        'gcego:make-page' => Commands\Make\MakePageCommand::class,
        'gcego:make-panel' => Commands\Make\MakePanelCommand::class,
        'gcego:make-form' => Commands\Make\MakeFormCommand::class,
        'gcego:make-table' => Commands\Make\MakeTableCommand::class,
        'gcego:make-infolist' => Commands\Make\MakeInfolistCommand::class,
        'gcego:make-widget' => Commands\Make\MakeWidgetCommand::class,
        'gcego:make-action' => Commands\Make\MakeActionCommand::class,
        'gcego:make-layout' => Commands\Make\MakeLayoutCommand::class,
        'gcego:make-navigation' => Commands\Make\MakeNavigationCommand::class,
        'gcego:make-notification' => Commands\Make\MakeNotificationCommand::class,
        'gcego:make-workflow' => Commands\Make\MakeWorkflowCommand::class,
        'gcego:make-settings' => Commands\Make\MakeSettingsCommand::class,
        'gcego:make-metric' => Commands\Make\MakeMetricCommand::class,
        'gcego:make-dataset' => Commands\Make\MakeDatasetCommand::class,
        'gcego:make-report' => Commands\Make\MakeReportCommand::class,
        'gcego:make-test' => Commands\Make\MakeTestCommand::class,
    ];

    /** Commands of other GCEGO packages the CLI relies on. */
    public const array Delegated = [
        'gcego:install' => 'gcego/studio',
        'gcego:registry-cache' => 'gcego/registry',
        'gcego:registry-clear' => 'gcego/registry',
        'gcego:studio:health' => 'gcego/studio',
    ];
}
