<?php

declare(strict_types=1);

namespace Gcego\Cli\Inspection;

use Gcego\Cli\Generation\PathResolver;
use Gcego\Studio\Compatibility\PackageCompatibilityChecker;
use Gcego\Studio\Integration\PackageIntegrationManager;

/**
 * gcego:packages: every GCEGO package — installed version, the constraint
 * the application requires, provider and registration status — from
 * gcego/studio's integration manager and compatibility checker.
 */
final readonly class PackageInspector
{
    public function __construct(private PackageIntegrationManager $packages, private PackageCompatibilityChecker $compatibility, private PathResolver $paths) {}

    /**
     * @return array{packages: list<array<string, mixed>>, compatible: bool, problems: list<string>, notices: list<string>, versions: array<string, mixed>}
     */
    public function report(): array
    {
        $constraints = $this->constraints();
        $packages = [];

        foreach ($this->packages->packages() as $status) {
            $packages[] = [...$status->toArray(), 'constraint' => $constraints[$status->id] ?? null];
        }

        if (! isset(array_column($packages, null, 'id')['gcego/cli'])) {
            $packages[] = ['id' => 'gcego/cli', 'required' => false, 'installed' => true, 'version' => PackageIntegrationManager::version('gcego/cli'), 'constraint' => $constraints['gcego/cli'] ?? null, 'purpose' => 'Developer CLI'];
        }

        $report = $this->compatibility->check();

        return ['packages' => $packages, 'compatible' => $report->compatible(), 'problems' => array_values($report->problems), 'notices' => array_values($report->notices), 'versions' => $report->versions];
    }

    /**
     * The application's own constraints on GCEGO packages (composer.json require and require-dev).
     *
     * @return array<string, string>
     */
    private function constraints(): array
    {
        $file = $this->paths->basePath().'/composer.json';
        $composer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $constraints = [];

        foreach (['require', 'require-dev'] as $section) {
            $requires = is_array($composer) && is_array($composer[$section] ?? null) ? $composer[$section] : [];

            foreach ($requires as $package => $constraint) {
                if (is_string($package) && str_starts_with($package, 'gcego/') && is_string($constraint)) {
                    $constraints[$package] = $constraint;
                }
            }
        }

        return $constraints;
    }
}
