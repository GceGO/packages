<?php

declare(strict_types=1);

namespace Gcego\Vault\Auditing;

use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Events\AccessChanged;
use Gcego\Vault\Models\AuditEntry;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;

/**
 * Writes the append-only access audit log and dispatches AccessChanged.
 *
 * Entries hold who acted, on whom, on what (role or permission name), in
 * which tenant, why, and small identifier payloads. Payload values are
 * reduced to scalars and short strings; nothing else (no record attributes,
 * request input, passwords or tokens) is ever written.
 */
final readonly class AuditLog
{
    public function __construct(private VaultConfig $config, private Dispatcher $events) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(AuditEvent $event, ?Holder $actor, ?Holder $subject = null, ?string $target = null, string $tenant = '', ?string $reason = null, array $payload = []): void
    {
        $payload = $this->clean($payload);
        $reason = $reason === null ? null : mb_substr($reason, 0, 500);

        if ($this->config->audit) {
            (new AuditEntry)->forceFill([
                'event' => $event,
                'actor_type' => $actor?->type,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'subject_type' => $subject?->type,
                'subject_id' => $subject?->id,
                'subject_name' => $subject?->name,
                'target' => $target === null ? null : mb_substr($target, 0, 200),
                'tenant' => $tenant,
                'reason' => $reason,
                'payload' => $payload === [] ? null : $payload,
                'created_at' => Date::now(),
            ])->save();
        }

        $this->events->dispatch(new AccessChanged($event, $actor, $subject, $target, $tenant, $reason, $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar|list<scalar>|null>
     */
    private function clean(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (preg_match('/(password|secret|token|recovery|remember)/i', $key) === 1) {
                continue;
            }

            $clean[$key] = match (true) {
                is_string($value) => mb_substr($value, 0, 200),
                is_int($value), is_float($value), is_bool($value), $value === null => $value,
                is_array($value) => array_values(array_map(static fn (mixed $item): string|int|float|bool => is_string($item) ? mb_substr($item, 0, 150) : (is_scalar($item) ? $item : '?'), array_slice(array_values($value), 0, 200))),
                default => null,
            };
        }

        return $clean;
    }
}
