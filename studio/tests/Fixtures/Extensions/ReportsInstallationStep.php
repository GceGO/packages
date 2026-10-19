<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests\Fixtures\Extensions;

use Gcego\Studio\Contracts\InstallationStep;
use Gcego\Studio\Installation\PlannedFile;

final class ReportsInstallationStep implements InstallationStep
{
    public static ?string $path = null;

    public function id(): string
    {
        return 'acme.reports.config';
    }

    public function files(): array
    {
        return [new PlannedFile(self::$path ?? config_path('acme-reports.php'), "<?php\n\nreturn ['enabled' => true];\n", 'Reports configuration')];
    }
}
