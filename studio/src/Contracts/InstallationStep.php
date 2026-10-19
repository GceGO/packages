<?php

declare(strict_types=1);

namespace Gcego\Studio\Contracts;

use Gcego\Studio\Installation\PlannedFile;

/**
 * Files an extension contributes to gcego:install (created only when
 * missing, or with --force; never outside the application).
 */
interface InstallationStep
{
    public function id(): string;

    /**
     * @return list<PlannedFile>
     */
    public function files(): array;
}
