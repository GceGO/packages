<?php

declare(strict_types=1);

namespace Gcego\Studio\Diagnostics;

use Gcego\Studio\Assets\StudioAssetManager;
use Gcego\Studio\Bootstrap\StudioBootstrapper;
use Gcego\Studio\Compatibility\PackageCompatibilityChecker;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Defaults\StudioDefaults;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Integration\FrameworkCatalog;
use Gcego\Studio\Integration\PackageIntegrationManager;
use Gcego\Studio\Studio;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Everything Studio knows about the assembled framework, as plain data
 * (gcego:studio:inspect, and the base of future CLI tooling): bootstrap,
 * packages, compatibility, appearance defaults, panels, modules, pages,
 * navigation, registry counts, assets and extension sections.
 *
 * It reads definitions through their own inspectors and never executes
 * actions, queries records or prints configuration values beyond the
 * documented settings. Absolute paths are made relative; in production
 * (unless gcego-studio.diagnostics.production_details) details and file
 * paths are left out.
 */
final readonly class StudioInspector
{
    public function __construct(
        private Application $app,
        private StudioConfiguration $config,
        private StudioBootstrapper $bootstrapper,
        private PackageIntegrationManager $packages,
        private PackageCompatibilityChecker $compatibility,
        private FrameworkCatalog $catalog,
        private StudioAssetManager $assets,
        private StudioDefaults $defaults,
        private StudioExtensionRegistry $extensions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $sections = [];

        foreach ($this->extensions->contexts() as $id => $context) {
            foreach ($context->sections() as $name => $section) {
                try {
                    $sections[$id.':'.$name] = $section();
                } catch (Throwable $exception) {
                    $sections[$id.':'.$name] = ['error' => $exception->getMessage()];
                }
            }
        }

        $report = [
            'studio' => ['version' => Studio::version(), 'enabled' => $this->config->enabled, 'environment' => $this->app->environment()],
            'bootstrap' => $this->bootstrapper->verify(true)->toArray(),
            'packages' => array_map(static fn ($status): array => $status->toArray(), $this->packages->packages()),
            'compatibility' => $this->compatibility->check()->toArray(),
            'appearance' => $this->defaults->effective(),
            'panels' => $this->catalog->panels(),
            'modules' => $this->catalog->modules(),
            'pages' => $this->catalog->pages(),
            'navigation' => $this->catalog->navigation(),
            'definitions' => $this->catalog->counts(),
            'assets' => $this->assets->inspect()->toArray(),
            'extensions' => $this->extensions->ids(),
            'sections' => $sections,
        ];

        /** @var array<string, mixed> $redacted */
        $redacted = $this->redact($report, $this->app->environment('production') && ! $this->config->productionDetails);

        return $redacted;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data, bool $production): array
    {
        $base = rtrim($this->app->basePath(), '/');
        $result = [];

        foreach ($data as $key => $value) {
            if ($production && in_array($key, ['details', 'middleware', 'path', 'paths'], true)) {
                continue;
            }

            $result[$key] = match (true) {
                is_array($value) => $this->redact($value, $production),
                is_string($value) => str_replace($base.'/', '', $value),
                default => $value,
            };
        }

        return $result;
    }
}
