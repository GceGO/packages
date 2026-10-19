<?php

declare(strict_types=1);

namespace Gcego\Settings\History;

use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\Types\SettingCaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use JsonException;

/**
 * Writes history entries inside the change's transaction (so history and
 * values commit or roll back together) and pages through them for one
 * scope instance. Reads are always limited to one target (scope and scope
 * key from a server-side context) and to the keys the caller may view,
 * and are paginated (at most 100 per page): never unbounded, never
 * across tenants.
 */
final readonly class SettingsHistoryRepository
{
    public function __construct(private SettingsConfig $config, private SettingCaster $caster = new SettingCaster) {}

    public function record(SettingDefinition $definition, ScopeContext $context, ChangeType $change, SettingsActor $actor, mixed $old, mixed $new, bool $hadOld, int $version, string $correlation): void
    {
        if (! $this->config->history) {
            return;
        }

        $entry = new SettingChange;
        $entry->forceFill([
            'ulid' => (string) Str::ulid(),
            'key' => $definition->key,
            'group' => $definition->group,
            'scope' => $context->target->value,
            'scope_key' => (string) $context->targetKey(),
            'tenant' => $context->tenant?->id(),
            'change' => $change->value,
            'actor_type' => $actor->type(),
            'actor_id' => $actor->id(),
            'actor_name' => $actor->name(),
            'old_value' => $definition->sensitive || ! $hadOld ? null : $this->safe($definition, $old),
            'new_value' => $definition->sensitive || in_array($change, [ChangeType::Reset, ChangeType::Requested, ChangeType::Refused], true) ? null : $this->safe($definition, $new),
            'redacted' => $definition->sensitive,
            'version' => $version,
            'correlation' => $correlation,
        ])->save();
    }

    /**
     * Newest first, for one target and these keys.
     *
     * @param  list<string>  $keys
     * @return LengthAwarePaginator<int, SettingChange>
     */
    public function page(ScopeContext $context, array $keys, int $page = 1, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = max(1, min(100, $perPage ?? $this->config->historyPerPage));

        return SettingChange::query()
            ->with('actor')
            ->where('scope', $context->target->value)
            ->where('scope_key', (string) $context->targetKey())
            ->whereIn('key', $keys === [] ? [''] : $keys)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'history', max(1, $page));
    }

    private function safe(SettingDefinition $definition, mixed $value): ?string
    {
        try {
            $text = json_encode($this->caster->plain($definition->type, $value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            return null;
        }

        return mb_strlen($text) > $this->config->historyValueLimit ? null : $text;
    }
}
