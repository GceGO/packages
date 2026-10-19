<?php

declare(strict_types=1);

namespace Gcego\Workflows;

use Gcego\Workflows\Exceptions\WorkflowDefinitionException;

/**
 * Validated gcego-workflows configuration (a container singleton, plain data).
 * No option turns server-side authorization or tenant checks into a bypass.
 */
final readonly class WorkflowsConfig
{
    public function __construct(
        public bool $optimisticLocking = true,
        public ?string $connection = null,
        public bool $commentOnRejection = true,
        public bool $commentOnChanges = true,
        public bool $selfApproval = false,
        public int $maxDelegations = 3,
        public bool $automation = true,
        public ?string $queue = 'workflows',
        public ?string $queueConnection = null,
        public int $tries = 3,
        public bool $deadlines = true,
        public bool $schedule = true,
        public int $batch = 200,
        public bool $notifications = true,
        public int $historyPerPage = 20,
        public int $inboxPerPage = 15,
        public ?string $logChannel = null,
        public bool $enforceTenantScope = true,
        public string $manageAbility = 'gcego-workflows.manage',
        public bool $panels = true,
    ) {
        foreach (['approvals.max_delegations' => [$maxDelegations, 0, 10], 'automation.tries' => [$tries, 1, 20], 'deadlines.batch' => [$batch, 1, 5000], 'history.per_page' => [$historyPerPage, 1, 200], 'inbox.per_page' => [$inboxPerPage, 1, 100]] as $key => [$value, $min, $max]) {
            if ($value < $min || $value > $max) {
                throw WorkflowDefinitionException::invalid('gcego-workflows.'.$key, sprintf('must be between %d and %d.', $min, $max));
            }
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $manageAbility) !== 1) {
            throw WorkflowDefinitionException::invalid('gcego-workflows.security.manage_ability', 'use a Gate ability name.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $get = static fn (string $key, mixed $default): mixed => data_get($config, $key, $default);
        $bool = static fn (string $key, bool $default): bool => is_bool($value = $get($key, $default)) ? $value : throw WorkflowDefinitionException::invalid('gcego-workflows.'.$key, 'must be true or false.');
        $int = static fn (string $key, int $default): int => is_int($value = $get($key, $default)) ? $value : throw WorkflowDefinitionException::invalid('gcego-workflows.'.$key, 'must be an integer.');
        $nullable = static fn (string $key, ?string $default): ?string => is_string($value = $get($key, $default)) || $value === null ? $value : throw WorkflowDefinitionException::invalid('gcego-workflows.'.$key, 'must be a string or null.');

        return new self(
            optimisticLocking: $bool('execution.optimistic_locking', true),
            connection: $nullable('execution.connection', null),
            commentOnRejection: $bool('approvals.require_comment_on_rejection', true),
            commentOnChanges: $bool('approvals.require_comment_on_changes', true),
            selfApproval: $bool('approvals.allow_self_approval', false),
            maxDelegations: $int('approvals.max_delegations', 3),
            automation: $bool('automation.enabled', true),
            queue: $nullable('automation.queue', 'workflows'),
            queueConnection: $nullable('automation.connection', null),
            tries: $int('automation.tries', 3),
            deadlines: $bool('deadlines.enabled', true),
            schedule: $bool('deadlines.schedule', true),
            batch: $int('deadlines.batch', 200),
            notifications: $bool('notifications.enabled', true),
            historyPerPage: $int('history.per_page', 20),
            inboxPerPage: $int('inbox.per_page', 15),
            logChannel: $nullable('observability.log_channel', null),
            enforceTenantScope: $bool('security.enforce_tenant_scope', true),
            manageAbility: is_string($ability = $get('security.manage_ability', 'gcego-workflows.manage')) ? $ability : throw WorkflowDefinitionException::invalid('gcego-workflows.security.manage_ability', 'must be a string.'),
            panels: $bool('panels.enabled', true),
        );
    }
}
