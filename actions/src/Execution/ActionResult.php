<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Illuminate\Database\Eloquent\Model;

/**
 * The immutable outcome of one execution, independent of any UI.
 *
 * Messages are safe and translated: exception details never appear (they
 * are reported to Laravel's exception handler instead). Livewire hosts,
 * HTTP endpoints and future gcego/notifications read the same result.
 */
final readonly class ActionResult
{
    /**
     * @param  array<string, list<string>>  $errors  Validation errors by field.
     * @param  array{token: string, title: string, message: string, confirm: string, cancel: string, destructive: bool}|null  $confirmation
     * @param  array<string, scalar|list<scalar>|null>  $data  Safe extra data (bulk summary).
     */
    public function __construct(
        public ActionStatus $status,
        public string $action,
        public string $message,
        public array $errors = [],
        public ?string $recordType = null,
        public string|int|null $recordKey = null,
        public ?string $redirect = null,
        public bool $refresh = false,
        public bool $closeDialog = false,
        public ?array $confirmation = null,
        public array $data = [],
        public ?string $reason = null,
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $data
     */
    public static function success(string $action, string $message, mixed $record = null, ?ActionEffects $effects = null, array $data = []): self
    {
        [$type, $key] = self::reference($record);

        return new self(ActionStatus::Success, $action, $effects?->customMessage() ?? $message, recordType: $type, recordKey: $key, redirect: $effects?->redirectTo(), refresh: $effects?->shouldRefresh() ?? true, closeDialog: $effects?->shouldCloseDialog() ?? true, data: $data);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function invalid(string $action, string $message, array $errors): self
    {
        return new self(ActionStatus::ValidationFailed, $action, $message, $errors);
    }

    public static function denied(string $action, string $message, int $status = 403, ?string $reason = null): self
    {
        return $status === 404
            ? new self(ActionStatus::NotFound, $action, $message, reason: $reason, closeDialog: true)
            : new self(ActionStatus::Denied, $action, $message, reason: $reason, closeDialog: true);
    }

    /**
     * @param  array{token: string, title: string, message: string, confirm: string, cancel: string, destructive: bool}  $confirmation
     */
    public static function confirmationRequired(string $action, string $message, array $confirmation): self
    {
        return new self(ActionStatus::ConfirmationRequired, $action, $message, confirmation: $confirmation);
    }

    public static function notFound(string $action, string $message): self
    {
        return new self(ActionStatus::NotFound, $action, $message, closeDialog: true);
    }

    public static function conflict(string $action, string $message, ?string $reason = null): self
    {
        return new self(ActionStatus::Conflict, $action, $message, reason: $reason);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $data
     */
    public static function failed(string $action, string $message, ?string $reason = null, array $data = []): self
    {
        return new self(ActionStatus::Failed, $action, $message, data: $data, reason: $reason);
    }

    public function successful(): bool
    {
        return $this->status === ActionStatus::Success;
    }

    public function httpStatus(): int
    {
        return $this->status->httpStatus();
    }

    /**
     * Safe for browsers and events: no record attributes, no exception details.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'action' => $this->action,
            'message' => $this->message,
            'errors' => $this->errors,
            'record_type' => $this->recordType,
            'record_key' => $this->recordKey,
            'redirect' => $this->redirect,
            'refresh' => $this->refresh,
            'data' => $this->data,
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{0: string|null, 1: string|int|null}
     */
    private static function reference(mixed $record): array
    {
        if (! $record instanceof Model) {
            return [null, null];
        }

        $key = $record->getKey();

        return [$record::class, is_int($key) || is_string($key) ? $key : null];
    }
}
