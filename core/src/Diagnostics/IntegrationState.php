<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

/** Core's own integration milestones, independent of whether framework auto-boot is enabled. */
final class IntegrationState
{
    public bool $registered = false;

    public bool $providerBooted = false;

    public bool $commandsRegistered = false;
}
