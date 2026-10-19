<?php

declare(strict_types=1);

namespace Gcego\Settings;

use Gcego\Settings\Contracts\SettingsRecipients;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Scopes\SettingScope;

/**
 * Validated gcego-settings configuration (a container singleton, plain
 * data). No option turns authorization, encryption of sensitive values or
 * tenant isolation off: those options exist to be explicit and must stay
 * true.
 */
final readonly class SettingsConfig
{
    /**
     * @param  list<string>  $discoveryPaths
     * @param  list<string>  $pagePaths
     * @param  list<SettingScope>  $scopes  Enabled scopes.
     * @param  list<SettingScope>  $precedence  Most specific first; every scope once.
     * @param  class-string<SettingsRecipients>|null  $recipients
     */
    public function __construct(
        public bool $enabled = true,
        public array $discoveryPaths = [],
        public array $pagePaths = [],
        public ?string $connection = null,
        public bool $cache = true,
        public ?string $cacheStore = null,
        public int $cacheTtl = 3600,
        public string $cachePrefix = 'gcego-settings',
        public array $scopes = [SettingScope::Global, SettingScope::Panel, SettingScope::Tenant, SettingScope::User],
        public array $precedence = [SettingScope::User, SettingScope::Tenant, SettingScope::Panel, SettingScope::Global],
        public string $manageAbility = 'gcego-settings.manage',
        public string $tenantPermission = 'settings.manage',
        public string $exportAbility = 'gcego-settings.export',
        public string $importAbility = 'gcego-settings.import',
        public bool $history = true,
        public int $historyPerPage = 20,
        public int $historyValueLimit = 2000,
        public bool $approvals = true,
        public bool $notifications = true,
        public ?string $recipients = null,
        public bool $panels = true,
        public string $panelPath = 'settings',
        public int $maxImport = 500,
        public ?string $logChannel = null,
    ) {
        foreach (['cache.ttl' => [$cacheTtl, 1, 2_592_000], 'history.per_page' => [$historyPerPage, 1, 100], 'history.value_limit' => [$historyValueLimit, 0, 65_000], 'transfer.max_settings' => [$maxImport, 1, 10_000]] as $key => [$value, $min, $max]) {
            if ($value < $min || $value > $max) {
                throw self::invalid($key, sprintf('must be between %d and %d.', $min, $max));
            }
        }

        foreach (['security.manage_ability' => $manageAbility, 'security.tenant_permission' => $tenantPermission, 'security.export_ability' => $exportAbility, 'security.import_ability' => $importAbility] as $key => $ability) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $ability) !== 1) {
                throw self::invalid($key, 'use a Gate ability or permission name.');
            }
        }

        if (count($precedence) !== count(SettingScope::cases())) {
            throw self::invalid('scopes.precedence', 'list each of user, tenant, panel and global exactly once.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9_.:-]{0,63}$/', $cachePrefix) !== 1) {
            throw self::invalid('cache.prefix', 'use letters, digits, ".", ":", "_" or "-".');
        }

        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $panelPath) !== 1) {
            throw self::invalid('panels.path', 'use a single lowercase path segment.');
        }

        if ($recipients !== null && ! is_a($recipients, SettingsRecipients::class, true)) {
            throw self::invalid('notifications.recipients', sprintf('must name a class implementing %s.', SettingsRecipients::class));
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $get = static fn (string $key, mixed $default): mixed => data_get($config, $key, $default);
        $bool = static fn (string $key, bool $default): bool => is_bool($value = $get($key, $default)) ? $value : throw self::invalid($key, 'must be true or false.');
        $int = static fn (string $key, int $default): int => is_int($value = $get($key, $default)) ? $value : throw self::invalid($key, 'must be an integer.');
        $string = static fn (string $key, string $default): string => is_string($value = $get($key, $default)) && $value !== '' ? $value : throw self::invalid($key, 'must be a non-empty string.');
        $nullable = static fn (string $key): ?string => is_string($value = $get($key, null)) || $value === null ? $value : throw self::invalid($key, 'must be a string or null.');
        $paths = static function (string $key) use ($get): array {
            $value = $get($key, []);
            $folders = [];

            foreach (is_string($value) ? [$value] : (is_array($value) ? $value : throw self::invalid($key, 'must be a list of folders.')) as $path) {
                $folders[] = is_string($path) ? $path : throw self::invalid($key, 'must be a list of folders.');
            }

            return $folders;
        };

        foreach (['security.require_authorization', 'security.encrypt_sensitive_values', 'security.enforce_tenant_scope'] as $mandatory) {
            if ($get($mandatory, true) !== true) {
                throw self::invalid($mandatory, 'must be true: GCEGO settings never skip authorization, encryption of sensitive values or tenant isolation.');
            }
        }

        if ($get('storage.driver', 'database') !== 'database') {
            throw self::invalid('storage.driver', 'must be "database".');
        }

        $scopes = [];

        foreach (SettingScope::cases() as $scope) {
            if ($bool('scopes.'.$scope->value, true)) {
                $scopes[] = $scope;
            }
        }

        $precedence = $get('scopes.precedence', ['user', 'tenant', 'panel', 'global']);

        return new self(
            enabled: $bool('enabled', true),
            discoveryPaths: $paths('discovery.paths'),
            pagePaths: $paths('discovery.pages'),
            connection: $nullable('storage.connection'),
            cache: $bool('cache.enabled', true),
            cacheStore: $nullable('cache.store'),
            cacheTtl: $int('cache.ttl', 3600),
            cachePrefix: $string('cache.prefix', 'gcego-settings'),
            scopes: $scopes,
            precedence: is_array($precedence) && array_is_list($precedence) && count(SettingScope::list($precedence)) === count($precedence) ? SettingScope::list($precedence) : throw self::invalid('scopes.precedence', 'list each of user, tenant, panel and global exactly once.'),
            manageAbility: $string('security.manage_ability', 'gcego-settings.manage'),
            tenantPermission: $string('security.tenant_permission', 'settings.manage'),
            exportAbility: $string('security.export_ability', 'gcego-settings.export'),
            importAbility: $string('security.import_ability', 'gcego-settings.import'),
            history: $bool('history.enabled', true),
            historyPerPage: $int('history.per_page', 20),
            historyValueLimit: $int('history.value_limit', 2000),
            approvals: $bool('approvals.enabled', true),
            notifications: $bool('notifications.enabled', true),
            recipients: ($recipients = $nullable('notifications.recipients')) === null || is_a($recipients, SettingsRecipients::class, true) ? $recipients : throw self::invalid('notifications.recipients', sprintf('must name a class implementing %s.', SettingsRecipients::class)),
            panels: $bool('panels.enabled', true),
            panelPath: $string('panels.path', 'settings'),
            maxImport: $int('transfer.max_settings', 500),
            logChannel: $nullable('observability.log_channel'),
        );
    }

    public function enables(SettingScope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    private static function invalid(string $key, string $reason): InvalidSettingException
    {
        return InvalidSettingException::definition('gcego-settings.'.$key, $reason);
    }
}
