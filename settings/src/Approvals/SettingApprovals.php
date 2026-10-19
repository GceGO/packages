<?php

declare(strict_types=1);

namespace Gcego\Settings\Approvals;

use Gcego\Settings\Contracts\SettingsRepository;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Events\SettingChangeRequested;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\Exceptions\SettingNotFoundException;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\History\ChangeType;
use Gcego\Settings\History\SettingsHistoryRepository;
use Gcego\Settings\Integration\SettingsNotifier;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Security\SettingEncryption;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\Support\Keys;
use Gcego\Settings\Types\SettingSerializer;
use Gcego\Settings\Writing\SettingsWriter;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Optional approval of changes through gcego/workflows (no second approval
 * engine). When a setting (or its group) names an approval workflow, a
 * change made by a person becomes a SettingRequest, the subject of a new
 * instance of that workflow; nothing is applied yet. The workflow's
 * automation applies it (ApplySettingRequest) or discards it
 * (DiscardSettingRequest). Applying re-validates the value, re-checks the
 * scope and refuses it when the setting changed since the request (the
 * base version), so an approval never overwrites a newer value.
 * Changes by system actors (trusted code) are not routed.
 */
final readonly class SettingApprovals
{
    public function __construct(
        private Container $container,
        private SettingsConfig $config,
        private SettingsDefinitions $definitions,
        private ScopeResolver $scopes,
        private SettingEncryption $encryption,
        private SettingSerializer $serializer,
        private SettingsHistoryRepository $history,
        private SettingsNotifier $notifier,
        private SettingsRepository $repository,
    ) {}

    public function requires(SettingDefinition $definition, SettingsActor $actor): bool
    {
        return $this->config->approvals && $definition->approval !== null && ! $actor->isSystem();
    }

    /**
     * Open a request and start its workflow (inside the writer's transaction).
     *
     * @throws InvalidSettingException when the workflow is not registered
     */
    public function open(SettingDefinition $definition, ScopeContext $context, mixed $value, SettingsActor $actor, int $baseVersion, string $correlation): SettingRequest
    {
        $workflow = (string) $definition->approval;

        if (! $this->container->bound(WorkflowEngine::class) || ! $this->container->make(WorkflowDefinitions::class)->has($workflow)) {
            throw InvalidSettingException::definition(sprintf('The setting "%s"', $definition->key), sprintf('needs the approval workflow "%s", which is not registered.', $workflow));
        }

        if (! $actor->user instanceof Model) {
            throw SettingAuthorizationException::for('request a change to', $definition->key);
        }

        $request = new SettingRequest;
        $request->forceFill([
            'ulid' => (string) Str::ulid(),
            'key' => $definition->key,
            'scope' => $context->target->value,
            'scope_key' => (string) $context->targetKey(),
            'tenant' => $context->tenant?->id(),
            'value' => $this->encryption->encrypt($this->serializer->serialize($definition, $value)),
            'type' => $definition->type->value,
            'base_version' => $baseVersion,
            'workflow' => $workflow,
            'status' => SettingRequest::Pending,
            'requested_by_type' => $actor->user->getMorphClass(),
            'requested_by_id' => Keys::string($actor->user->getKey()),
        ])->save();

        $this->history->record($definition, $context, ChangeType::Requested, $actor, null, null, false, $baseVersion, $correlation);
        $this->container->make(WorkflowEngine::class)->start($workflow, $request, $actor->user, 'settings-request.'.$request->ulid, $context->tenant === null ? null : (string) $context->tenant->key);
        $event = new SettingChangeRequested($definition->key, $definition->group, $context->target->value, $context->tenant?->id(), $workflow, $request->ulid, $actor->reference(), $correlation);
        $this->repository->afterCommit(function () use ($event): void {
            $this->container->make(Dispatcher::class)->dispatch($event);
        });

        return $request;
    }

    /**
     * Apply an approved request (idempotent).
     */
    public function apply(SettingRequest $request): void
    {
        if (! $request->isPending()) {
            return;
        }

        $label = $request->key;

        try {
            $definition = $this->definitions->definition($request->key);
            $label = $definition->label();
            $value = $this->serializer->unserialize($definition, $this->encryption->decrypt((string) $request->value));
            $this->container->make(SettingsWriter::class)->write($this->context($request), [$request->key => $value], SettingsActor::system('approvals'), [$request->key => $request->base_version], ChangeType::Approved);
            $status = SettingRequest::Applied;
        } catch (SettingConflictException) {
            $status = SettingRequest::Conflicted;
        } catch (InvalidSettingException|SettingScopeException|SettingNotFoundException) {
            $status = SettingRequest::Invalid;
        }

        $this->close($request, $status, $label);
    }

    /**
     * Close a refused or cancelled request (idempotent).
     */
    public function refuse(SettingRequest $request): void
    {
        if (! $request->isPending()) {
            return;
        }

        $label = $request->key;

        try {
            $definition = $this->definitions->definition($request->key);
            $label = $definition->label();
            $this->history->record($definition, $this->context($request), ChangeType::Refused, SettingsActor::system('approvals'), null, null, false, $request->base_version, (string) Str::uuid());
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->close($request, SettingRequest::Refused, $label);
    }

    /**
     * The scope a request was made for, rebuilt from its stored (server-written) target.
     *
     * @throws SettingScopeException
     */
    public function context(SettingRequest $request): ScopeContext
    {
        $scope = SettingScope::tryFrom($request->scope) ?? throw SettingScopeException::because('The request has an unknown scope.');

        return match ($scope) {
            SettingScope::Global => $this->scopes->global(),
            SettingScope::Panel => $this->scopes->forPanel($request->scope_key),
            SettingScope::Tenant => $this->tenantContext($request),
            SettingScope::User => new ScopeContext(SettingScope::User, user: $request->scope_key),
        };
    }

    private function tenantContext(SettingRequest $request): ScopeContext
    {
        $id = (string) $request->tenant;
        $tenant = $this->scopes->tenant(substr($id, (int) strpos($id, ':') + 1));

        if ($tenant->id() !== $id || $tenant->id() !== $request->scope_key) {
            throw SettingScopeException::because('The request belongs to another tenant.');
        }

        return new ScopeContext(SettingScope::Tenant, tenant: $tenant);
    }

    private function close(SettingRequest $request, string $status, string $label): void
    {
        $request->forceFill(['status' => $status, 'value' => null, 'decided_at' => now()])->save();
        $this->notifier->decided($request, $label);
    }
}
