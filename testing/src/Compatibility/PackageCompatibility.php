<?php

declare(strict_types=1);

namespace Gcego\Testing\Compatibility;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;

/**
 * Checks the platform and the GCEGO packages the toolkit integrates with,
 * reading Composer's installed versions (never a hard-coded list of
 * versions from elsewhere).
 */
final readonly class PackageCompatibility
{
    /**
     * Lowest supported major versions.
     */
    public const array Platform = ['php' => '8.3.0', 'laravel/framework' => '13', 'livewire/livewire' => '4', 'phpunit/phpunit' => '12'];

    /**
     * Package => service provider. Anything else under "gcego/" that is
     * installed is reported too, as an unknown optional package.
     */
    public const array Packages = [
        'gcego/morfaw' => null,
        'gcego/core' => 'Gcego\Core\GcegoServiceProvider',
        'gcego/blueprints' => null,
        'gcego/registry' => 'Gcego\Registry\RegistryServiceProvider',
        'gcego/runtime' => 'Gcego\Runtime\RuntimeServiceProvider',
        'gcego/chroma' => 'Gcego\Chroma\ChromaServiceProvider',
        'gcego/data' => 'Gcego\Data\DataServiceProvider',
        'gcego/security' => 'Gcego\Security\SecurityServiceProvider',
        'gcego/actions' => 'Gcego\Actions\ActionsServiceProvider',
        'gcego/forms' => 'Gcego\Forms\FormsServiceProvider',
        'gcego/tables' => 'Gcego\Tables\TablesServiceProvider',
        'gcego/infolists' => 'Gcego\Infolists\InfolistsServiceProvider',
        'gcego/widgets' => 'Gcego\Widgets\WidgetsServiceProvider',
        'gcego/layouts' => 'Gcego\Layouts\LayoutsServiceProvider',
        'gcego/navigation' => 'Gcego\Navigation\NavigationServiceProvider',
        'gcego/pages' => 'Gcego\Pages\PagesServiceProvider',
        'gcego/modules' => 'Gcego\Modules\ModulesServiceProvider',
        'gcego/panels' => 'Gcego\Panels\PanelsServiceProvider',
        'gcego/notifications' => 'Gcego\Notifications\NotificationsServiceProvider',
        'gcego/media' => 'Gcego\Media\MediaServiceProvider',
        'gcego/workflows' => 'Gcego\Workflows\WorkflowsServiceProvider',
        'gcego/settings' => 'Gcego\Settings\SettingsServiceProvider',
        'gcego/analytics' => 'Gcego\Analytics\AnalyticsServiceProvider',
        'gcego/studio' => 'Gcego\Studio\StudioServiceProvider',
        'gcego/cli' => 'Gcego\Cli\CliServiceProvider',
        'gcego/sentinel' => 'Gcego\Sentinel\SentinelServiceProvider',
    ];

    public function __construct(private ?Application $app = null) {}

    /**
     * @return list<CompatibilityResult>
     */
    public function check(): array
    {
        $results = [$this->php()];

        foreach (['laravel/framework', 'livewire/livewire', 'phpunit/phpunit'] as $package) {
            $results[] = $this->platform($package);
        }

        foreach (self::Packages as $package => $provider) {
            $results[] = $this->gcego($package, $provider);
        }

        return $results;
    }

    /**
     * @return list<CompatibilityResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->check(), static fn (CompatibilityResult $result): bool => ! $result->passes()));
    }

    public function version(string $package): ?string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($package);
    }

    private function php(): CompatibilityResult
    {
        return version_compare(PHP_VERSION, self::Platform['php'], '>=')
            ? new CompatibilityResult('php', CompatibilityResult::Ok, PHP_VERSION, 'Supported.')
            : new CompatibilityResult('php', CompatibilityResult::Incompatible, PHP_VERSION, sprintf('PHP %s or newer is required.', self::Platform['php']));
    }

    private function platform(string $package): CompatibilityResult
    {
        $version = $this->version($package);
        $minimum = self::Platform[$package];

        if ($version === null) {
            $optional = $package === 'livewire/livewire';

            return new CompatibilityResult($package, $optional ? CompatibilityResult::Optional : CompatibilityResult::Missing, null, $optional ? 'Not installed: the Livewire adapter is unavailable.' : sprintf('Install %s %s or newer.', $package, $minimum));
        }

        $major = (int) ltrim($version, 'v');

        return $major >= (int) $minimum
            ? new CompatibilityResult($package, CompatibilityResult::Ok, $version, 'Supported.')
            : new CompatibilityResult($package, CompatibilityResult::Incompatible, $version, sprintf('Version %s or newer is required; upgrade with "composer require %s:^%s".', $minimum, $package, $minimum));
    }

    private function gcego(string $package, ?string $provider): CompatibilityResult
    {
        $version = $this->version($package);

        if ($version === null) {
            return new CompatibilityResult($package, CompatibilityResult::Optional, null, 'Not installed: its assertions are unavailable.');
        }

        if ($provider !== null && ! class_exists($provider)) {
            return new CompatibilityResult($package, CompatibilityResult::Incompatible, $version, sprintf('Installed, but its service provider %s does not exist (the package may be incomplete).', $provider));
        }

        if ($provider !== null && $this->app !== null && $this->app->getProviders($provider) === []) {
            return new CompatibilityResult($package, CompatibilityResult::Incompatible, $version, sprintf('Installed, but %s is not registered in this application.', $provider));
        }

        return new CompatibilityResult($package, CompatibilityResult::Ok, $version, 'Installed and registered.');
    }
}
