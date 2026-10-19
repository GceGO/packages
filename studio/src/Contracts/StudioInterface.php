<?php

declare(strict_types=1);

namespace Gcego\Studio\Contracts;

use Gcego\Studio\Bootstrap\BootstrapResult;
use Gcego\Studio\Compatibility\CompatibilityReport;
use Gcego\Studio\Health\HealthReport;
use Gcego\Studio\Integration\PackageStatus;

/**
 * The stable public API of GCEGO Admin Studio.
 */
interface StudioInterface
{
    /**
     * @return list<string>
     */
    public function panels(): array;

    /**
     * @return list<string>
     */
    public function modules(): array;

    /**
     * @return list<string>
     */
    public function pages(): array;

    /**
     * @return list<PackageStatus>
     */
    public function packages(): array;

    public function hasCapability(string $capability): bool;

    public function health(bool $production = false): HealthReport;

    public function compatibility(): CompatibilityReport;

    public function bootstrap(): BootstrapResult;

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array;

    /**
     * @param  class-string<StudioExtension>|StudioExtension  $extension
     */
    public function extend(string|StudioExtension $extension): void;
}
