<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests\Fixtures\Extensions;

use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Extensions\StudioExtensionContext;

/**
 * A trusted Studio extension: a health check, an installation step, a panel extension and an inspector section.
 */
final class ReportsExtension implements StudioExtension
{
    public static int $registered = 0;

    public function id(): string
    {
        return 'acme/reports';
    }

    public function priority(): int
    {
        return 50;
    }

    public function register(StudioExtensionContext $context): void
    {
        self::$registered++;
        $context->healthCheck(ReportsHealthCheck::class)
            ->installationStep(ReportsInstallationStep::class)
            ->panelExtension(ReportsPanelExtension::class)
            ->inspectorSection('reports', static fn (): array => ['reports' => 3]);
    }
}
