<?php

declare(strict_types=1);

namespace Gcego\Security;

use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Security\Contracts\RoleProvider;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Exceptions\MissingAuthorizationContextException;

/**
 * Validated gcego-security configuration. Invalid or unsafe values fail at boot.
 */
final readonly class SecurityConfig
{
    /**
     * @param  class-string<PermissionProvider>|'gate'|'spatie'  $permissionProvider
     * @param  class-string<RoleProvider>|'spatie'|null  $roleProvider
     * @param  class-string<TenantResolver>|null  $tenantResolver
     * @param  array<class-string, string>  $tenantModels  Model class or interface => tenant key column.
     * @param  list<string>  $auditSensitive  fnmatch patterns of abilities/permissions reported when granted.
     */
    public function __construct(
        public ?string $guard = null,
        public DenialStrategy $denialStrategy = DenialStrategy::Forbidden,
        public string $permissionProvider = 'gate',
        public ?string $roleProvider = null,
        public bool $tenancy = false,
        public ?string $tenantResolver = null,
        public string $tenantParameter = 'tenant',
        public string $tenantSessionKey = 'gcego.tenant',
        public DenialStrategy $tenantDenialStrategy = DenialStrategy::Conceal,
        public array $tenantModels = [],
        public string $fieldMask = '********',
        public bool $audit = false,
        public ?string $auditChannel = null,
        public bool $auditDenials = true,
        public array $auditSensitive = [],
    ) {
        if ($tenancy && $tenantResolver === null) {
            throw self::invalid('tenancy.resolver must name a TenantResolver class when tenancy is enabled.');
        }

        if ($tenantResolver !== null && ! is_a($tenantResolver, TenantResolver::class, true)) {
            throw self::invalid(sprintf('tenancy.resolver %s does not implement %s.', $tenantResolver, TenantResolver::class));
        }

        if (! in_array($permissionProvider, ['gate', 'spatie'], true) && ! is_a($permissionProvider, PermissionProvider::class, true)) {
            throw self::invalid(sprintf('permissions.provider must be "gate", "spatie" or a %s class.', PermissionProvider::class));
        }

        if ($roleProvider !== null && $roleProvider !== 'spatie' && ! is_a($roleProvider, RoleProvider::class, true)) {
            throw self::invalid(sprintf('roles.provider must be null, "spatie" or a %s class.', RoleProvider::class));
        }
    }

    /**
     * @param  array<mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $default = data_get($config, 'authorization.default', 'deny');

        if ($default !== 'deny') {
            throw self::invalid('authorization.default must be "deny": GCEGO security never allows by default.');
        }

        $string = static function (string $path, ?string $fallback) use ($config): ?string {
            $value = data_get($config, $path, $fallback);

            return $value === null || (is_string($value) && $value !== '') ? $value : throw self::invalid(sprintf('%s must be a non-empty string.', $path));
        };
        $bool = static function (string $path, bool $fallback) use ($config): bool {
            $value = data_get($config, $path, $fallback);

            return is_bool($value) ? $value : throw self::invalid(sprintf('%s must be a boolean.', $path));
        };
        $status = static function (string $path, DenialStrategy $fallback) use ($config): DenialStrategy {
            $value = data_get($config, $path, $fallback->value);

            return (is_int($value) ? DenialStrategy::tryFrom($value) : null) ?? throw self::invalid(sprintf('%s must be 403 or 404.', $path));
        };
        $models = data_get($config, 'tenancy.models', []);
        $sensitive = data_get($config, 'audit.sensitive', []);

        if (! is_array($models) || array_filter($models, static fn (mixed $column, mixed $model): bool => ! is_string($model) || ! (class_exists($model) || interface_exists($model)) || ! is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1, ARRAY_FILTER_USE_BOTH) !== []) {
            throw self::invalid('tenancy.models must map existing model classes or interfaces to tenant key columns.');
        }

        if (! is_array($sensitive) || array_filter($sensitive, static fn (mixed $pattern): bool => ! is_string($pattern)) !== []) {
            throw self::invalid('audit.sensitive must be a list of ability patterns.');
        }

        /** @var array<class-string, string> $models */
        /** @var list<string> $sensitive */
        /** @var class-string<PermissionProvider>|'gate'|'spatie' $permissionProvider */
        $permissionProvider = $string('permissions.provider', 'gate') ?? 'gate';
        /** @var class-string<RoleProvider>|'spatie'|null $roleProvider */
        $roleProvider = $string('roles.provider', null);
        /** @var class-string<TenantResolver>|null $tenantResolver */
        $tenantResolver = $string('tenancy.resolver', null);

        return new self(
            guard: $string('authorization.guard', null),
            denialStrategy: $status('authorization.denial_status', DenialStrategy::Forbidden),
            permissionProvider: $permissionProvider,
            roleProvider: $roleProvider,
            tenancy: $bool('tenancy.enabled', false),
            tenantResolver: $tenantResolver,
            tenantParameter: $string('tenancy.parameter', 'tenant') ?? 'tenant',
            tenantSessionKey: $string('tenancy.session_key', 'gcego.tenant') ?? 'gcego.tenant',
            tenantDenialStrategy: $status('tenancy.denial_status', DenialStrategy::Conceal),
            tenantModels: $models,
            fieldMask: $string('fields.mask', '********') ?? '********',
            audit: $bool('audit.enabled', false),
            auditChannel: $string('audit.channel', null),
            auditDenials: $bool('audit.denials', true),
            auditSensitive: array_values($sensitive),
        );
    }

    private static function invalid(string $reason): MissingAuthorizationContextException
    {
        return MissingAuthorizationContextException::because('Invalid gcego-security configuration: '.$reason);
    }
}
