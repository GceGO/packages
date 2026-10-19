<?php

declare(strict_types=1);

namespace Gcego\Settings\Support;

use Gcego\Settings\SettingsConfig;
use Illuminate\Log\LogManager;
use Throwable;

/**
 * Structured settings activity on gcego-settings.observability.log_channel
 * (nothing when null). Entries carry keys, scopes, versions, actors and
 * correlation ids: never values, so secrets cannot reach logs.
 */
final readonly class SettingsLogger
{
    public function __construct(private SettingsConfig $config, private LogManager $logs) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $context
     */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $context
     */
    private function write(string $level, string $event, array $context): void
    {
        if ($this->config->logChannel === null) {
            return;
        }

        try {
            $this->logs->channel($this->config->logChannel)->log($level, 'gcego-settings.'.$event, $context);
        } catch (Throwable) {
            // Observability never breaks a settings operation.
        }
    }
}
