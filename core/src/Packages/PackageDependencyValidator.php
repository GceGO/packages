<?php

declare(strict_types=1);

namespace Gcego\Core\Packages;

use Composer\Semver\Semver;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Support\DependencyGraph;
use Gcego\Core\Support\ExclusiveCapabilities;

/**
 * Validates GCEGO package dependencies and computes the boot order.
 *
 * Problems are reported in this order: disabled requirements, unsupported
 * versions, missing requirements, a dependency cycle, then exclusive
 * capabilities offered by more than one enabled package.
 *
 * This works only on framework-level descriptors of packages that Composer
 * has already installed. It never resolves versions or installs anything.
 */
final class PackageDependencyValidator
{
    /**
     * The boot order of the enabled packages.
     *
     * @param  list<PackageDescriptor>  $descriptors
     * @param  list<string>  $disabled
     * @return list<string>
     *
     * @throws PackageDependencyException for the first problem found.
     */
    public function resolve(array $descriptors, array $disabled = []): array
    {
        $graph = $this->graph($descriptors, $disabled);
        $problems = $this->collectProblems($descriptors, $disabled, $graph);

        if ($problems !== []) {
            throw $problems[0];
        }

        return $graph->order();
    }

    /**
     * Every dependency problem, without throwing (used by diagnostics).
     *
     * @param  list<PackageDescriptor>  $descriptors
     * @param  list<string>  $disabled
     * @return list<PackageDependencyException>
     */
    public function problems(array $descriptors, array $disabled = []): array
    {
        return $this->collectProblems($descriptors, $disabled, $this->graph($descriptors, $disabled));
    }

    /**
     * @param  list<PackageDescriptor>  $descriptors
     * @param  list<string>  $disabled
     * @return list<PackageDependencyException>
     */
    private function collectProblems(array $descriptors, array $disabled, DependencyGraph $graph): array
    {
        $problems = [];
        $registered = [];
        $isDisabled = array_fill_keys($disabled, true);
        $enabled = [];

        foreach ($descriptors as $descriptor) {
            $registered[$descriptor->id] = $descriptor;

            if (! isset($isDisabled[$descriptor->id])) {
                $enabled[] = $descriptor;
            }
        }

        foreach ($enabled as $descriptor) {
            $disabledRequirements = array_values(array_filter(
                $descriptor->requires,
                static fn (string $id): bool => isset($registered[$id], $isDisabled[$id]),
            ));

            if ($disabledRequirements !== []) {
                $problems[] = PackageDependencyException::disabled($descriptor->id, $disabledRequirements);
            }
        }

        foreach ($enabled as $descriptor) {
            foreach ($descriptor->versionConstraints as $dependency => $constraint) {
                $version = $registered[$dependency]->version ?? null;
                if ($version === null || isset($isDisabled[$dependency])) {
                    continue;
                }
                try {
                    if (! Semver::satisfies($version, $constraint)) {
                        $problems[] = new PackageDependencyException(sprintf('GCEGO package "%s" has an unsupported version of dependency "%s".', $descriptor->id, $dependency), diagnosticCode: 'packages.dependencies.unsupported_version');
                    }
                } catch (\UnexpectedValueException) {
                    $problems[] = new PackageDependencyException(sprintf('GCEGO package "%s" cannot verify the declared version of dependency "%s".', $descriptor->id, $dependency), diagnosticCode: 'packages.dependencies.unsupported_version');
                }
            }
        }

        foreach ($graph->missing() as $id => $missing) {
            $missing = array_values(array_filter($missing, static fn (string $dependency): bool => ! isset($registered[$dependency])));

            if ($missing !== []) {
                $problems[] = PackageDependencyException::missing($id, $missing);
            }
        }

        $cycle = $graph->cycle();

        if ($cycle !== null) {
            $problems[] = PackageDependencyException::circular($cycle);
        }

        foreach (ExclusiveCapabilities::conflicts($enabled) as $capability => $providers) {
            $problems[] = PackageDependencyException::exclusiveCapability($capability, $providers);
        }

        return $problems;
    }

    /**
     * @param  list<PackageDescriptor>  $descriptors
     * @param  list<string>  $disabled
     */
    private function graph(array $descriptors, array $disabled): DependencyGraph
    {
        $nodes = [];
        $isDisabled = array_fill_keys($disabled, true);

        foreach ($descriptors as $descriptor) {
            if (! isset($isDisabled[$descriptor->id])) {
                $nodes[$descriptor->id] = ['requires' => $descriptor->requires, 'optional' => $descriptor->optional];
            }
        }

        return new DependencyGraph($nodes);
    }
}
