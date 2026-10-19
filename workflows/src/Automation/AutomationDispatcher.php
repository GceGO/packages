<?php

declare(strict_types=1);

namespace Gcego\Workflows\Automation;

use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\Jobs\ExecuteWorkflowAutomation;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;

/**
 * Schedules automation for after the command commits: queued (the
 * configured queue) or run right after the commit. Only handlers named
 * by definitions are scheduled, by name; the job looks them up again.
 */
final readonly class AutomationDispatcher
{
    public function __construct(private Container $container, private WorkflowsConfig $config, private LoggerInterface $logger) {}

    public function schedule(WorkflowInstance $instance, Automation $automation, string $origin, string $correlation, Effects $effects): void
    {
        if (! $this->config->automation) {
            $this->logger->warning('gcego-workflows: automation is disabled; "'.$automation->nodeName().'" did not run.', ['instance' => $instance->ulid, 'origin' => $origin]);

            return;
        }

        $id = $instance->id;
        $name = $automation->nodeName();
        $queued = $automation->queued();

        $effects->after(function () use ($id, $instance, $name, $origin, $correlation, $queued): void {
            $job = new ExecuteWorkflowAutomation($id, $name, $origin, $instance->state_sequence, $correlation);

            if ($queued) {
                $job->onQueue($this->config->queue)->onConnection($this->config->queueConnection);
                $job->tries = $this->config->tries;
                $this->container->make(Dispatcher::class)->dispatch($job);

                return;
            }

            $job->tries = 1;
            $this->container->make(Dispatcher::class)->dispatchSync($job);
        });
    }
}
