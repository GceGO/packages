<?php

declare(strict_types=1);

namespace Gcego\Core\Packages;

use Composer\Semver\Semver;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Support\DependencyGraph;

/**
 * Validates GCEGO package dependencies and computes the boot order.
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
        $problems = $this->problems($descriptors, $disabled);

        if ($problems !== []) {
            throw $problems[0];
        }

        return $this->graph($descriptors, $disabled)->order();
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
        $problems = [];
        $registered = [];

        foreach ($descriptors as $descriptor) {
            $registered[$descriptor->id] = $descriptor;
        }

        foreach ($descriptors as $descriptor) {
            if (in_array($descriptor->id, $disabled, true)) {
                continue;
            }

            $disabledRequirements = array_values(array_filter(
                $descriptor->requires,
                static fn (string $id): bool => isset($registered[$id]) && in_array($id, $disabled, true),
            ));

            if ($disabledRequirements !== []) {
                $problems[] = PackageDependencyException::disabled($descriptor->id, $disabledRequirements);
            }
        }

        foreach ($descriptors as $descriptor) {
            if (in_array($descriptor->id, $disabled, true)) {
                continue;
            }
            foreach ($descriptor->versionConstraints as $dependency => $constraint) {
                $version = $registered[$dependency]->version ?? null;
                if ($version === null || in_array($dependency, $disabled, true)) {
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

        $graph = $this->graph($descriptors, $disabled);

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

        return $problems;
    }

    /**
     * @param  list<PackageDescriptor>  $descriptors
     * @param  list<string>  $disabled
     */
    private function graph(array $descriptors, array $disabled): DependencyGraph
    {
        $nodes = [];

        foreach ($descriptors as $descriptor) {
            if (! in_array($descriptor->id, $disabled, true)) {
                $nodes[$descriptor->id] = ['requires' => $descriptor->requires, 'optional' => $descriptor->optional];
            }
        }

        return new DependencyGraph($nodes);
    }
}
