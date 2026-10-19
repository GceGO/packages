<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Assets\StudioAssetManager;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;

/**
 * Vite, Tailwind CSS v4, gcego/chroma and Alpine integration (see StudioAssetManager).
 */
final class AssetsCheck implements StudioHealthCheck
{
    public function __construct(private readonly StudioAssetManager $assets, private readonly bool $productionMode = false) {}

    public function id(): string
    {
        return $this->productionMode ? 'production.assets' : 'assets.integration';
    }

    public function production(): bool
    {
        return $this->productionMode;
    }

    public function run(): HealthCheckResult
    {
        $report = $this->assets->inspect();

        if ($this->productionMode) {
            if ($report->devServer) {
                return HealthCheckResult::failure($this->id(), 'Production assets', 'public/hot exists: pages load assets from the Vite dev server.', 'Stop "npm run dev", delete public/hot and run "npm run build".', 'gcego/studio');
            }

            return $report->problems === [] && $report->manifestFound
                ? HealthCheckResult::pass($this->id(), 'Production assets', 'The Vite production manifest lists every panel entry.', 'gcego/studio')
                : HealthCheckResult::failure($this->id(), 'Production assets', $report->problems === [] ? 'No Vite production manifest was found.' : implode(' ', $report->problems), 'Run "npm run build" during deployment.', 'gcego/studio');
        }

        if ($report->problems !== []) {
            return HealthCheckResult::failure($this->id(), 'Assets', implode(' ', $report->problems), 'Build the assets (npm run build) or start the dev server (npm run dev).', 'gcego/studio');
        }

        if ($report->warnings !== []) {
            return HealthCheckResult::warning($this->id(), 'Assets', implode(' ', $report->warnings), 'Adjust the CSS and JS entries as described.', 'gcego/chroma');
        }

        return HealthCheckResult::pass($this->id(), 'Assets', $report->entries === [] ? 'No panel Vite entries are configured; panels use their built-in styles.' : sprintf('%d Vite entries; Tailwind CSS v4 and gcego/chroma are wired.', count($report->entries)), 'gcego/studio');
    }
}
