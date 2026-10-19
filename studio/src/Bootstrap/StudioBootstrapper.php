<?php

declare(strict_types=1);

namespace Gcego\Studio\Bootstrap;

use Gcego\Core\Gcego;
use Gcego\Panels\PanelManager;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Exceptions\IncompatiblePackageException;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Integration\PackageIntegrationManager;
use Throwable;

/**
 * Studio's part of the Laravel boot: once the application booted it
 * applies the trusted extensions (their panel extensions go to
 * gcego/panels before routes are built), then verify() checks — on
 * demand, never on every request — that the required packages are
 * integrated, the configuration is valid and gcego/core booted.
 * Idempotent; no queries.
 */
final class StudioBootstrapper
{
    private bool $applied = false;

    private ?BootstrapResult $result = null;

    public function __construct(
        private readonly StudioExtensionRegistry $extensions,
        private readonly PanelManager $panels,
        private readonly PackageIntegrationManager $packages,
        private readonly Gcego $gcego,
        private readonly StudioConfiguration $config,
    ) {}

    /**
     * Applies extensions' contributions (once).
     */
    public function apply(): void
    {
        if ($this->applied) {
            return;
        }

        $this->applied = true;

        foreach ($this->extensions->contexts() as $context) {
            foreach ($context->panelExtensions() as $extension) {
                $this->panels->extend($extension);
            }
        }
    }

    public function isApplied(): bool
    {
        return $this->applied;
    }

    public function verify(bool $fresh = false): BootstrapResult
    {
        if ($this->result !== null && ! $fresh) {
            return $this->result;
        }

        $started = hrtime(true);
        $kernelProblems = [];

        try {
            $boot = $this->gcego->bootResult() ?? $this->gcego->boot();
            $kernel = $boot->status->value;

            if (! $boot->isBooted()) {
                $kernelProblems[] = sprintf('gcego/core did not boot (status "%s").', $kernel);
            }
        } catch (Throwable $exception) {
            $kernel = 'failed';
            $kernelProblems[] = 'gcego/core: '.$exception->getMessage();
        }

        $problems = [...$this->packages->problems(), ...$kernelProblems];

        if (! $this->config->enabled) {
            $problems[] = 'Studio is disabled (gcego-studio.enabled).';
        }

        return $this->result = new BootstrapResult($problems === [], $problems, count(PackageIntegrationManager::Required), $this->extensions->ids(), $kernel, round((hrtime(true) - $started) / 1e6, 3));
    }

    /**
     * @throws IncompatiblePackageException
     */
    public function assertReady(): void
    {
        $result = $this->verify();

        if (! $result->ok) {
            throw IncompatiblePackageException::because($result->problems);
        }
    }
}
