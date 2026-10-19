<?php

declare(strict_types=1);

namespace Gcego\Settings\Transfer;

use Gcego\Settings\Cache\SettingsCache;
use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Types\SettingCaster;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Exports the overrides stored at one scope instance (not inherited
 * values) as a versioned, JSON-safe document:
 *
 *     {"format": "gcego-settings", "version": 1, "scope": "tenant", "exported_at": "…",
 *      "settings": {"school_name": {"type": "string", "value": "…"}}, "redacted": ["sms_api_key"]}
 *
 * Needs the export ability and, for each setting, the right to view it.
 * Sensitive settings are never exported, not even encrypted: they are
 * listed under "redacted" so the receiving side knows to set them again.
 */
final readonly class SettingsExporter
{
    public const string Format = 'gcego-settings';

    public const int Version = 1;

    public function __construct(
        private SettingsDefinitions $definitions,
        private SettingsAuthorizer $authorizer,
        private SettingsCache $cache,
        private SettingsReader $reader,
        private SettingCaster $caster = new SettingCaster,
    ) {}

    /**
     * @param  list<string>|null  $groups  Limit to these groups.
     * @return array{format: string, version: int, scope: string, exported_at: string, settings: array<string, array{type: string, value: mixed}>, redacted: list<string>}
     *
     * @throws SettingAuthorizationException
     */
    public function export(ScopeContext $context, ?Authenticatable $user, ?array $groups = null): array
    {
        if (! $this->authorizer->canExport($user, $context)) {
            throw SettingAuthorizationException::for('export');
        }

        $target = (string) $context->targetKey();
        $stored = $this->cache->scopes([['scope' => $context->target, 'key' => $target]])[$context->target->value.'|'.$target] ?? [];
        $settings = [];
        $redacted = [];

        foreach ($this->definitions->definitions() as $key => $definition) {
            if (($groups !== null && ! in_array($definition->group, $groups, true)) || ! isset($stored[$key]) || ! $this->authorizer->canView($user, $definition, $context)) {
                continue;
            }

            if ($definition->sensitive) {
                $redacted[] = $key;

                continue;
            }

            try {
                $settings[$key] = ['type' => $definition->type->value, 'value' => $this->caster->plain($definition->type, $this->reader->decode($definition, $stored[$key]))];
            } catch (InvalidSettingException) {
                continue;
            }
        }

        ksort($settings);
        sort($redacted);

        return ['format' => self::Format, 'version' => self::Version, 'scope' => $context->target->value, 'exported_at' => now()->toAtomString(), 'settings' => $settings, 'redacted' => $redacted];
    }
}
