<?php

declare(strict_types=1);

namespace Gcego\Vault;

use Gcego\Vault\Enums\SyncMode;
use Gcego\Vault\Exceptions\InvalidConfigurationException;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Support\Names;
use Illuminate\Database\Eloquent\Model;

/**
 * Validated gcego-vault configuration. Invalid or unsafe values fail at boot,
 * so a typo can never silently widen access.
 */
final readonly class VaultConfig
{
    /** Applied when the application's configuration does not name dependencies (mergeConfigFrom is shallow). */
    public const array DefaultDependencies = ['view' => ['viewAny'], 'create' => ['viewAny'], 'update' => ['view'], 'delete' => ['view'], 'restore' => ['view'], 'forceDelete' => ['view']];

    /**
     * @param  list<class-string<Model>>  $subjects
     * @param  array<string, array{label: string, description: string|null, group: string|null, scope: string, sensitive: bool, requires: list<string>}>  $permissions
     * @param  list<string>  $abilities
     * @param  array<string, list<string>>  $dependencies
     * @param  array<string, array{label: string, description: string|null, permissions: list<string>, inherits: list<string>, protected: bool, super: bool, panel: string|null, group: string|null, sync: SyncMode}>  $roles
     * @param  list<string>  $superExcluded
     * @param  list<string>  $protectedRoles
     * @param  list<string>|null  $panelsOnly
     */
    public function __construct(
        public bool $enabled = true,
        public ?string $connection = null,
        public array $subjects = [],
        public bool $securityPermissions = true,
        public bool $securityRoles = true,
        public bool $securityTenancy = true,
        public bool $gate = true,
        public array $permissions = [],
        public bool $discoverModules = true,
        public bool $discoverPanels = true,
        public bool $discoverRequirements = true,
        public array $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
        public array $dependencies = [],
        public array $roles = [],
        public bool $superAdmin = false,
        public array $superExcluded = [],
        public array $protectedRoles = [],
        public bool $sensitiveRequiresApproval = false,
        public int $approvals = 1,
        public int $sensitiveApprovals = 2,
        public ?int $maxTemporaryDays = 365,
        public bool $requests = true,
        public int $maxPending = 10,
        public bool $cache = true,
        public ?string $cacheStore = null,
        public int $cacheTtl = 3600,
        public string $cachePrefix = 'gcego-vault',
        public bool $audit = true,
        public ?int $retentionDays = null,
        public bool $panels = true,
        public string $panelPath = 'vault',
        public string $navigationGroup = 'Access control',
        public ?array $panelsOnly = null,
    ) {}

    /**
     * @param  array<mixed>  $config
     * @param  class-string<Model>|null  $userModel  auth.providers.users.model, the default subject.
     */
    public static function fromArray(array $config, ?string $userModel = null): self
    {
        $bool = static function (string $path, bool $default) use ($config): bool {
            $value = data_get($config, $path, $default);

            return is_bool($value) ? $value : throw InvalidConfigurationException::because($path.' must be a boolean.');
        };
        $int = static function (string $path, ?int $default, int $min, bool $nullable = false) use ($config): ?int {
            $value = data_get($config, $path, $default);

            if ($value === null && $nullable) {
                return null;
            }

            return is_int($value) && $value >= $min ? $value : throw InvalidConfigurationException::because(sprintf('%s must be an integer of at least %d.', $path, $min));
        };
        $string = static function (string $path, ?string $default, bool $nullable = false) use ($config): ?string {
            $value = data_get($config, $path, $default);

            if ($value === null && $nullable) {
                return null;
            }

            return is_string($value) && $value !== '' ? $value : throw InvalidConfigurationException::because($path.' must be a non-empty string.');
        };
        $list = static function (string $path, array $default, ?callable $valid = null) use ($config): array {
            $value = data_get($config, $path, $default);

            if (! is_array($value) || ! array_is_list($value) || array_filter($value, static fn (mixed $item): bool => ! is_string($item) || ($valid !== null && ! $valid($item))) !== []) {
                throw InvalidConfigurationException::because($path.' must be a list of valid names.');
            }

            /** @var list<string> $value */
            return $value;
        };

        $subjects = data_get($config, 'subjects') ?? ($userModel === null ? [] : [$userModel]);
        $subjects = is_string($subjects) ? [$subjects] : $subjects;

        if (! is_array($subjects) || array_filter($subjects, static fn (mixed $model): bool => ! is_string($model) || ! is_a($model, Model::class, true)) !== []) {
            throw InvalidConfigurationException::because('subjects must list Eloquent model classes.');
        }

        $approvals = (int) $int('governance.approvals', 1, 1);
        $sensitiveApprovals = (int) $int('governance.sensitive_approvals', 2, 1);

        if ($sensitiveApprovals < $approvals) {
            throw InvalidConfigurationException::because('governance.sensitive_approvals cannot be lower than governance.approvals.');
        }

        $abilities = $list('permissions.abilities', ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'], static fn (string $ability): bool => preg_match('/^[a-z][A-Za-z0-9]*$/', $ability) === 1);
        $dependencies = data_get($config, 'permissions.dependencies', self::DefaultDependencies);

        if (! is_array($dependencies) || array_filter($dependencies, static fn (mixed $requires, mixed $ability): bool => ! is_string($ability) || ! is_array($requires) || array_filter($requires, static fn (mixed $item): bool => ! is_string($item) || preg_match('/^[a-z][A-Za-z0-9]*$/', $item) !== 1) !== [], ARRAY_FILTER_USE_BOTH) !== []) {
            throw InvalidConfigurationException::because('permissions.dependencies must map abilities to lists of abilities.');
        }

        /** @var array<string, list<string>> $dependencies */
        $panelsOnly = data_get($config, 'panels.only');

        if ($panelsOnly !== null && (! is_array($panelsOnly) || array_filter($panelsOnly, static fn (mixed $panel): bool => ! is_string($panel)) !== [])) {
            throw InvalidConfigurationException::because('panels.only must be null or a list of panel names.');
        }

        $prefix = (string) $string('cache.prefix', 'gcego-vault');

        if (preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $prefix) !== 1) {
            throw InvalidConfigurationException::because('cache.prefix may only contain letters, digits, ".", ":", "_" and "-".');
        }

        $path = (string) $string('panels.path', 'vault');

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $path) !== 1) {
            throw InvalidConfigurationException::because('panels.path must be a lowercase URL segment.');
        }

        return new self(
            enabled: $bool('enabled', true),
            connection: $string('connection', null, nullable: true),
            subjects: array_values($subjects),
            securityPermissions: $bool('security.permissions', true),
            securityRoles: $bool('security.roles', true),
            securityTenancy: $bool('security.tenancy', true),
            gate: $bool('security.gate', true),
            permissions: self::permissions(data_get($config, 'permissions.definitions', [])),
            discoverModules: $bool('permissions.discovery.modules', true),
            discoverPanels: $bool('permissions.discovery.panels', true),
            discoverRequirements: $bool('permissions.discovery.requirements', true),
            abilities: $abilities,
            dependencies: $dependencies,
            roles: self::roles(data_get($config, 'roles', [])),
            superAdmin: $bool('super_admin.enabled', false),
            superExcluded: $list('super_admin.excluded', []),
            protectedRoles: $list('governance.protected_roles', [], Names::isRole(...)),
            sensitiveRequiresApproval: $bool('governance.sensitive_requires_approval', false),
            approvals: $approvals,
            sensitiveApprovals: $sensitiveApprovals,
            maxTemporaryDays: $int('governance.max_temporary_days', 365, 1, nullable: true),
            requests: $bool('requests.enabled', true),
            maxPending: (int) $int('requests.max_pending', 10, 1),
            cache: $bool('cache.enabled', true),
            cacheStore: $string('cache.store', null, nullable: true),
            cacheTtl: (int) $int('cache.ttl', 3600, 1),
            cachePrefix: $prefix,
            audit: $bool('audit.enabled', true),
            retentionDays: $int('audit.retention_days', null, 1, nullable: true),
            panels: $bool('panels.enabled', true),
            panelPath: $path,
            navigationGroup: (string) $string('panels.navigation_group', 'Access control'),
            panelsOnly: $panelsOnly === null ? null : array_values($panelsOnly),
        );
    }

    public function showsIn(string $panel): bool
    {
        return $this->panels && ($this->panelsOnly === null || in_array($panel, $this->panelsOnly, true));
    }

    public function isProtected(string $role): bool
    {
        return in_array($role, $this->protectedRoles, true) || (bool) ($this->roles[$role]['protected'] ?? false);
    }

    public function superExcludes(string $permission): bool
    {
        foreach ($this->superExcluded as $pattern) {
            if (Names::matches($pattern, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{label: string, description: string|null, group: string|null, scope: string, sensitive: bool, requires: list<string>}>
     */
    private static function permissions(mixed $definitions): array
    {
        if (! is_array($definitions)) {
            throw InvalidConfigurationException::because('permissions.definitions must map permission names to definitions.');
        }

        $permissions = [];

        foreach ($definitions as $name => $definition) {
            [$name, $definition] = is_int($name) ? [$definition, []] : [$name, $definition];

            if (! is_string($name) || ! Names::isPermission($name) || ! is_array($definition)) {
                throw InvalidConfigurationException::because(sprintf('permissions.definitions: "%s" is not a valid permission name.', is_string($name) ? $name : gettype($name)));
            }

            $scope = $definition['scope'] ?? Permission::ScopeAny;
            $requires = $definition['requires'] ?? [];

            if (! in_array($scope, [Permission::ScopeGlobal, Permission::ScopeTenant, Permission::ScopeAny], true) || ! is_array($requires) || array_filter($requires, static fn (mixed $item): bool => ! is_string($item) || ! Names::isPermission($item)) !== []) {
                throw InvalidConfigurationException::because(sprintf('permissions.definitions.%s has an invalid scope or requires.', $name));
            }

            $permissions[$name] = [
                'label' => is_string($definition['label'] ?? null) ? $definition['label'] : Names::label($name),
                'description' => is_string($definition['description'] ?? null) ? $definition['description'] : null,
                'group' => is_string($definition['group'] ?? null) ? $definition['group'] : null,
                'scope' => $scope,
                'sensitive' => ($definition['sensitive'] ?? false) === true,
                'requires' => array_values($requires),
            ];
        }

        return $permissions;
    }

    /**
     * @return array<string, array{label: string, description: string|null, permissions: list<string>, inherits: list<string>, protected: bool, super: bool, panel: string|null, group: string|null, sync: SyncMode}>
     */
    private static function roles(mixed $definitions): array
    {
        if (! is_array($definitions)) {
            throw InvalidConfigurationException::because('roles must map role names to definitions.');
        }

        $roles = [];

        foreach ($definitions as $name => $definition) {
            if (! is_string($name) || ! Names::isRole($name) || ! is_array($definition)) {
                throw InvalidConfigurationException::because(sprintf('roles: "%s" is not a valid role name.', is_string($name) ? $name : gettype($name)));
            }

            $permissions = $definition['permissions'] ?? [];
            $inherits = $definition['inherits'] ?? [];
            $sync = SyncMode::tryFrom(is_string($definition['sync'] ?? null) ? $definition['sync'] : SyncMode::Create->value);

            if (! is_array($permissions) || array_filter($permissions, static fn (mixed $item): bool => ! is_string($item) || ($item !== '*' && preg_match('/^[a-zA-Z0-9_.*-]+$/', $item) !== 1)) !== []) {
                throw InvalidConfigurationException::because(sprintf('roles.%s.permissions must list permission names or patterns.', $name));
            }

            if (! is_array($inherits) || array_filter($inherits, static fn (mixed $item): bool => ! is_string($item) || ! Names::isRole($item) || $item === $name) !== [] || $sync === null) {
                throw InvalidConfigurationException::because(sprintf('roles.%s has invalid inherits or sync.', $name));
            }

            $panel = $definition['panel'] ?? null;

            $roles[$name] = [
                'label' => is_string($definition['label'] ?? null) ? $definition['label'] : Names::label($name),
                'description' => is_string($definition['description'] ?? null) ? $definition['description'] : null,
                'permissions' => array_values($permissions),
                'inherits' => array_values($inherits),
                'protected' => ($definition['protected'] ?? false) === true,
                'super' => ($definition['super'] ?? false) === true,
                'panel' => is_string($panel) ? $panel : null,
                'group' => is_string($definition['group'] ?? null) ? $definition['group'] : null,
                'sync' => $sync,
            ];
        }

        return $roles;
    }
}
