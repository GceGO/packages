<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Actions\ActionManager;
use Gcego\Actions\ActionsConfig;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Executes one gcego/actions action through ActionManager::execute(), the
 * same pipeline the Livewire host uses: resolution, availability, record
 * lookup in scope, authorization, idempotency, confirmation token, input
 * validation, transaction, effects and the ActionCompleted event.
 *
 * confirmed() answers a confirmation the way a user does: the first
 * request returns a signed token and a second request sends it back.
 */
final class ActionTestable extends Testable
{
    private string|int|null $record = null;

    /** @var list<string|int> */
    private array $selection = [];

    /** @var array<string, mixed> */
    private array $input = [];

    private bool $confirm = false;

    private ?string $idempotencyKey = null;

    private ?ActionResult $result = null;

    private ?ActionResult $confirmationResult = null;

    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string $definition, private readonly string $action)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    public function record(string|int|Model $record): self
    {
        $key = $record instanceof Model ? $record->getKey() : $record;
        $this->record = is_int($key) || is_string($key) ? $key : null;

        return $this;
    }

    /**
     * Keys selected for a bulk action.
     *
     * @param  iterable<string|int|Model>  $records
     */
    public function select(iterable $records): self
    {
        $this->selection = [];

        foreach ($records as $record) {
            $key = $record instanceof Model ? $record->getKey() : $record;

            if (is_int($key) || is_string($key)) {
                $this->selection[] = $key;
            }
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $input  Submitted (untrusted) input.
     */
    public function input(array $input): self
    {
        $this->input = $input;

        return $this;
    }

    /**
     * When the action asks for confirmation, confirm it with the issued
     * token in a second request.
     */
    public function confirmed(bool $confirm = true): self
    {
        $this->confirm = $confirm;

        return $this;
    }

    public function idempotencyKey(?string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function execute(): self
    {
        $manager = $this->manager();
        $result = $manager->execute($this->request());
        $this->confirmationResult = null;

        if ($this->confirm && $result->status === ActionStatus::ConfirmationRequired) {
            $this->confirmationResult = $result;
            $token = is_string($result->confirmation['token'] ?? null) ? $result->confirmation['token'] : null;
            $result = $manager->execute($this->request()->withConfirmation((string) $token));
        }

        $this->result = $result;

        return $this;
    }

    public function result(): ActionResult
    {
        if ($this->result === null) {
            throw new LogicException('Call execute() before reading the action result.');
        }

        return $this->result;
    }

    public function resolved(): ResolvedAction
    {
        return $this->manager()->resolve($this->definition, $this->action);
    }

    public function assertSuccessful(): self
    {
        return $this->assertStatus(ActionStatus::Success);
    }

    public function assertStatus(ActionStatus|string $status): self
    {
        $status = $status instanceof ActionStatus ? $status : ActionStatus::from($status);
        $result = $this->result();

        return $this->check($result->status === $status, sprintf('assertStatus(%s)', $status->value), sprintf('Expected the action to end with [%s].', $status->value), ['status' => $status->value], ['status' => $result->status->value, 'reason' => $result->reason, 'message' => $result->message, 'errors' => $result->errors]);
    }

    /**
     * Refused by authorization (403, or 404 when concealed), optionally
     * with a reason such as "no_requirement" or "selection_unavailable".
     */
    public function assertDenied(?string $reason = null): self
    {
        $result = $this->result();

        return $this->check($result->status === ActionStatus::Denied && ($reason === null || $result->reason === $reason), sprintf('assertDenied(%s)', $this->export($reason)), 'Expected the action to be denied.', ['status' => 'denied', 'reason' => $reason], ['status' => $result->status->value, 'reason' => $result->reason, 'message' => $result->message]);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(ActionStatus::NotFound);
    }

    /**
     * Validation failed; with field names, each has an error.
     *
     * @param  list<string>  $fields
     */
    public function assertValidationFailed(array $fields = []): self
    {
        $result = $this->result();
        $missing = array_values(array_diff($fields, array_keys($result->errors)));

        return $this->check($result->status === ActionStatus::ValidationFailed && $missing === [], 'assertValidationFailed('.$this->export($fields).')', 'Expected the input to fail validation'.($fields !== [] ? ' for '.implode(', ', $fields) : '').'.', ['status' => 'validation_failed', 'fields' => $fields], ['status' => $result->status->value, 'errors' => $result->errors]);
    }

    public function assertConfirmationRequired(): self
    {
        $result = $this->confirmationResult ?? $this->result();

        return $this->check($result->status === ActionStatus::ConfirmationRequired && is_string($result->confirmation['token'] ?? null), 'assertConfirmationRequired()', 'Expected the action to ask for confirmation first.', ['status' => 'confirmation_required'], ['status' => $result->status->value]);
    }

    public function assertFailed(?string $reason = null): self
    {
        $result = $this->result();

        return $this->check($result->status === ActionStatus::Failed && ($reason === null || $result->reason === $reason), sprintf('assertFailed(%s)', $this->export($reason)), 'Expected the action to fail.', ['status' => 'failed', 'reason' => $reason], ['status' => $result->status->value, 'reason' => $result->reason]);
    }

    public function assertReason(string $reason): self
    {
        $result = $this->result();

        return $this->check($result->reason === $reason, sprintf('assertReason(%s)', $this->export($reason)), sprintf('Expected the reason [%s].', $reason), ['reason' => $reason], ['reason' => $result->reason, 'status' => $result->status->value]);
    }

    public function assertMessage(string $message): self
    {
        $result = $this->result();

        return $this->check(str_contains($result->message, $message), sprintf('assertMessage(%s)', $this->export($message)), 'Expected the result message to contain the text.', ['message' => $message], ['message' => $result->message]);
    }

    public function assertRedirect(?string $url = null): self
    {
        $result = $this->result();

        return $this->check($result->redirect !== null && ($url === null || $result->redirect === $url), sprintf('assertRedirect(%s)', $this->export($url)), 'Expected the action to redirect.', ['redirect' => $url ?? '(any)'], ['redirect' => $result->redirect]);
    }

    /**
     * The counts a bulk action reports; pass null to skip a count.
     */
    public function assertBulk(?int $processed = null, ?int $skipped = null, ?int $failed = null, ?int $selected = null): self
    {
        $data = $this->result()->data;
        $expected = array_filter(['selected' => $selected, 'processed' => $processed, 'skipped' => $skipped, 'failed' => $failed], static fn (?int $count): bool => $count !== null);
        $actual = array_intersect_key($data, $expected);

        return $this->check($actual == $expected, 'assertBulk('.$this->export($expected).')', 'Expected these bulk counts.', $expected, $data);
    }

    /**
     * The action is offered to the acting user (not hidden, authorized,
     * for the record when set). Display only: execution checks again.
     */
    public function assertAvailable(?string $placement = null): self
    {
        $available = $this->availableNames($placement);

        return $this->check(in_array($this->resolved()->name(), $available, true), sprintf('assertAvailable(%s)', $this->export($placement)), 'Expected the action to be offered to the acting user.', ['available' => true], ['available actions' => $available]);
    }

    public function assertUnavailable(?string $placement = null): self
    {
        $available = $this->availableNames($placement);

        return $this->check(! in_array($this->resolved()->name(), $available, true), sprintf('assertUnavailable(%s)', $this->export($placement)), 'Expected the action not to be offered to the acting user.', ['available' => false], ['available actions' => $available], 'Hiding an action is not authorization: also execute() it and assertDenied().');
    }

    public function assertRequiresConfirmation(bool $required = true): self
    {
        $actual = $this->resolved()->requiresConfirmation($this->container->make(ActionsConfig::class));

        return $this->check($actual === $required, 'assertRequiresConfirmation('.$this->export($required).')', $required ? 'Expected the action to require confirmation.' : 'Expected the action not to require confirmation.', ['confirmation' => $required], ['confirmation' => $actual]);
    }

    protected function subject(): string
    {
        return 'action';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['Definition' => $this->definition, 'Action' => $this->action, 'Record' => $this->record ?? '(none)', 'Selection' => $this->selection, 'User' => $user === null ? 'guest' : $user->getAuthIdentifier(), 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    private function request(): ActionRequest
    {
        return new ActionRequest($this->definition, $this->action, $this->record, $this->selection, $this->input, null, $this->idempotencyKey);
    }

    /**
     * @return list<string>
     */
    private function availableNames(?string $placement): array
    {
        return array_map(static fn (ResolvedAction $action): string => $action->name(), $this->manager()->available($this->definition, $placement, $this->record));
    }

    private function manager(): ActionManager
    {
        return $this->container->make(ActionManager::class);
    }
}
