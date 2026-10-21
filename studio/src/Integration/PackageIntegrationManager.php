<?php

declare(strict_types=1);

namespace Gcego\Studio\Integration;

use Composer\InstalledVersions;
use Gcego\Core\Gcego;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * The GCEGO packages Studio assembles. It registers nothing itself:
 * Composer installs the packages, Laravel's package discovery loads their
 * providers and gcego/core registers and boots them in dependency order.
 * Studio only verifies that each one got there, and detects the optional
 * packages (capabilities) without depending on them.
 */
final readonly class PackageIntegrationManager
{
    /** The eighteen foundation packages: id => [provider (null: a plain library), purpose]. */
    public const array Required = [
        'gcego/morfaw' => [null, 'Component tree, properties and validation'],
        'gcego/core' => ['Gcego\Core\GcegoServiceProvider', 'Framework kernel: packages, extensions, boot'],
        'gcego/blueprints' => [null, 'Blueprint composition'],
        'gcego/registry' => ['Gcego\Registry\RegistryServiceProvider', 'Definition discovery and cache'],
        'gcego/runtime' => ['Gcego\Runtime\RuntimeServiceProvider', 'Rendering and Livewire hosting'],
        'gcego/chroma' => ['Gcego\Chroma\ChromaServiceProvider', 'Tailwind CSS v4 slots, presets and themes'],
        'gcego/data' => ['Gcego\Data\DataServiceProvider', 'Data sources and scoped queries'],
        'gcego/security' => ['Gcego\Security\SecurityServiceProvider', 'Authorization and tenancy'],
        'gcego/actions' => ['Gcego\Actions\ActionsServiceProvider', 'Action pipeline'],
        'gcego/forms' => ['Gcego\Forms\FormsServiceProvider', 'Forms'],
        'gcego/tables' => ['Gcego\Tables\TablesServiceProvider', 'Tables'],
        'gcego/infolists' => ['Gcego\Infolists\InfolistsServiceProvider', 'Infolists'],
        'gcego/widgets' => ['Gcego\Widgets\WidgetsServiceProvider', 'Widgets and charts'],
        'gcego/layouts' => ['Gcego\Layouts\LayoutsServiceProvider', 'Layouts'],
        'gcego/navigation' => ['Gcego\Navigation\NavigationServiceProvider', 'Navigation and breadcrumbs'],
        'gcego/pages' => ['Gcego\Pages\PagesServiceProvider', 'Pages and page routes'],
        'gcego/modules' => ['Gcego\Modules\ModulesServiceProvider', 'Modules and resources'],
        'gcego/panels' => ['Gcego\Panels\PanelsServiceProvider', 'Panels and the panel shell'],
    ];

    /** Optional packages Studio recognizes (never required). */
    public const array Optional = [
        'gcego/notifications' => ['Gcego\Notifications\NotificationsServiceProvider', 'Notifications, toasts and inboxes'],
        'gcego/media' => ['Gcego\Media\MediaServiceProvider', 'Uploads and the media library'],
        'gcego/workflows' => ['Gcego\Workflows\WorkflowsServiceProvider', 'Workflows and approvals'],
        'gcego/settings' => ['Gcego\Settings\SettingsServiceProvider', 'Settings'],
        'gcego/analytics' => ['Gcego\Analytics\AnalyticsServiceProvider', 'Analytics and reports'],
        'gcego/testing' => ['Gcego\Testing\TestingServiceProvider', 'Testing toolkit'],
        'gcego/cli' => ['Gcego\Cli\CliServiceProvider', 'Developer CLI'],
        'gcego/sentinel' => ['Gcego\Sentinel\SentinelServiceProvider', 'Authentication engine and account security'],
    ];

    public function __construct(private Application $app, private Gcego $gcego) {}

    /**
     * @return list<PackageStatus>
     */
    public function packages(bool $optional = true): array
    {
        $statuses = [];

        foreach (self::Required as $id => [$provider, $purpose]) {
            $statuses[] = $this->status($id, true, $provider, $purpose);
        }

        foreach ($optional ? self::Optional : [] as $id => [$provider, $purpose]) {
            $statuses[] = $this->status($id, false, $provider, $purpose);
        }

        return $statuses;
    }

    public function package(string $id): ?PackageStatus
    {
        foreach ($this->packages() as $status) {
            if ($status->id === $id) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Problems with the required packages (empty: fully integrated).
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        // Registration and boot are gcego/core's, at boot: before it booted, only installation and discovery can be judged.
        $booted = $this->gcego->isBooted();

        foreach ($this->packages(false) as $status) {
            $problems = [...$problems, ...match (true) {
                ! $status->installed => [sprintf('%s is not installed (composer require %s).', $status->id, $status->id)],
                $status->provider !== null && ! $status->providerLoaded => [sprintf('%s is installed but its service provider %s is not loaded; check "dont-discover" in composer.json or register it.', $status->id, $status->provider)],
                ! $booted => [],
                $status->provider !== null && ! $status->registered => [sprintf('%s is not registered with gcego/core (it may be disabled in gcego.packages.disabled).', $status->id)],
                $status->provider !== null && ! $status->active => [sprintf('%s did not boot; run gcego:studio:health for the reason.', $status->id)],
                default => [],
            }];
        }

        return $problems;
    }

    /**
     * Whether an optional capability is available (installed, loaded and booted).
     */
    public function has(string $package): bool
    {
        $status = $this->package($package);

        return $status !== null && $status->integrated();
    }

    private function status(string $id, bool $required, ?string $provider, string $purpose): PackageStatus
    {
        $installed = self::installed($id) || ($provider !== null && class_exists($provider));
        $loaded = $provider !== null && $this->app->providerIsLoaded($provider);
        $registered = false;
        $active = false;
        $capabilities = [];

        try {
            $registered = $this->gcego->hasPackage($id);
            $active = $registered && $this->gcego->isPackageActive($id);
            $capabilities = $registered ? $this->gcego->package($id)->capabilities : [];
        } catch (Throwable) {
        }

        return new PackageStatus($id, $required, $installed, $installed ? self::version($id) : null, $provider, $loaded, $registered, $active, $capabilities, $purpose);
    }

    public static function installed(string $id): bool
    {
        try {
            return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($id);
        } catch (Throwable) {
            return false;
        }
    }

    public static function version(string $id): ?string
    {
        try {
            return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($id) ? InstalledVersions::getPrettyVersion($id) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
