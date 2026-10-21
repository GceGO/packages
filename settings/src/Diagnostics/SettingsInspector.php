<?php

declare(strict_types=1);

namespace Gcego\Settings\Diagnostics;

use Gcego\Forms\FormManager;
use Gcego\Security\SecurityConfig;
use Gcego\Settings\Approvals\SettingRequest;
use Gcego\Settings\Contracts\SettingsRepository;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Persistence\SettingRecord;
use Gcego\Settings\Persistence\StoredValue;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Security\SettingEncryption;
use Gcego\Settings\SettingsConfig;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * What an operator needs to know (gcego:settings:inspect, and future
 * gcego/cli): registered groups, settings and pages; definition problems;
 * whether the tables exist; stored values that no longer decode, belong
 * to no registered setting, or sit at a scope their setting does not
 * allow; sensitive values stored without encryption; the cache store;
 * whether encryption works; Gate abilities, tenancy and approval
 * workflows the definitions rely on; and the effective configuration.
 *
 * Read-only, bounded (at most $limit stored rows are examined), and it
 * never reports values: keys, scopes, counts and reasons only.
 */
final readonly class SettingsInspector
{
    public function __construct(
        private Container $container,
        private SettingsDefinitions $definitions,
        private SettingsConfig $config,
        private SettingsReader $reader,
        private SettingEncryption $encryption,
        private SecurityConfig $security,
    ) {}

    /**
     * @return array{groups: list<array<string, mixed>>, pages: list<array<string, mixed>>, problems: list<string>, warnings: list<string>, runtime: array<string, mixed>, config: array<string, mixed>}
     */
    public function report(bool $runtime = true, int $limit = 5000): array
    {
        $problems = $this->definitions->problems();
        $warnings = [];
        $groups = $pages = [];
        $definitions = [];

        if ($problems === []) {
            try {
                $definitions = $this->definitions->definitions();

                foreach ($this->definitions->groups() as $group) {
                    $groups[] = [
                        'name' => $group->groupName(),
                        'title' => $group->title(),
                        'settings' => array_map(static fn (SettingDefinition $definition): array => [
                            'key' => $definition->key,
                            'type' => $definition->type->value,
                            'scopes' => array_map(static fn (SettingScope $scope): string => $scope->value, $definition->scopes),
                            'precedence' => array_map(static fn (SettingScope $scope): string => $scope->value, $definition->precedence),
                            'sensitive' => $definition->sensitive,
                            'approval' => $definition->approval,
                            'requirements' => count($definition->requirements),
                        ], $this->definitions->inGroup($group->groupName())),
                    ];
                }

                foreach ($this->definitions->pages() as $page) {
                    $pages[] = ['name' => $page->pageName(), 'title' => $page->title(), 'group' => $page->groupName(), 'scope' => $page->scope()->value, 'panels' => $page->panels()];
                }
            } catch (Throwable $exception) {
                $problems[] = $exception->getMessage();
            }
        }

        foreach ($definitions as $key => $definition) {
            if ($definition->scopes === []) {
                $warnings[] = sprintf('The setting "%s" allows no enabled scope: it can only be read (its default).', $key);
            }

            if ($definition->allows(SettingScope::Tenant) && ! $this->security->tenancy) {
                $warnings[] = sprintf('The setting "%s" allows the tenant scope, but gcego/security tenancy is disabled.', $key);
            }

            if ($definition->approval !== null && $this->config->approvals && ! $this->workflowExists($definition->approval)) {
                $problems[] = sprintf('The setting "%s" needs the approval workflow "%s", which is not registered.', $key, $definition->approval);
            }
        }

        $gate = $this->container->make(Gate::class);

        foreach (['security.manage_ability' => $this->config->manageAbility, 'security.export_ability' => $this->config->exportAbility, 'security.import_ability' => $this->config->importAbility] as $option => $ability) {
            if (! $gate->has($ability)) {
                $warnings[] = sprintf('The Gate ability "%s" (gcego-settings.%s) is not defined: only settings with their own requirements can be managed at the global and panel scopes.', $ability, $option);
            }
        }

        if (! $this->encryption->available()) {
            $problems[] = 'Encryption does not work (check APP_KEY): sensitive settings cannot be stored or read.';
        }

        foreach (['gcego-settings-form', 'gcego-settings-panel'] as $component) {
            if ($this->config->enabled && ! $this->livewireHas($component)) {
                $problems[] = sprintf('The Livewire component "%s" is not registered.', $component);
            }
        }

        if (! class_exists(FormManager::class)) {
            $problems[] = 'gcego/forms is missing: settings forms cannot be generated.';
        }

        $runtimeReport = $runtime ? $this->runtime($definitions, $limit, $problems, $warnings) : [];

        return [
            'groups' => $groups,
            'pages' => $pages,
            'problems' => $problems,
            'warnings' => $warnings,
            'runtime' => $runtimeReport,
            'config' => [
                'enabled' => $this->config->enabled,
                'scopes' => array_map(static fn (SettingScope $scope): string => $scope->value, $this->config->scopes),
                'precedence' => array_map(static fn (SettingScope $scope): string => $scope->value, $this->config->precedence),
                'cache' => $this->config->cache,
                'cache_store' => $this->config->cacheStore ?? (is_string($store = config('cache.default')) ? $store : null),
                'cache_ttl' => $this->config->cacheTtl,
                'history' => $this->config->history,
                'approvals' => $this->config->approvals,
                'panels' => $this->config->panels,
                'tenancy' => $this->security->tenancy,
                'encryption' => $this->encryption->available(),
            ],
        ];
    }

    /**
     * @param  array<string, SettingDefinition>  $definitions
     * @param  list<string>  $problems
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function runtime(array $definitions, int $limit, array &$problems, array &$warnings): array
    {
        try {
            $connection = (new SettingRecord)->getConnection();
            $schema = Schema::connection($connection->getName());
            $missing = array_values(array_filter(['gcego_settings', 'gcego_setting_changes', 'gcego_setting_requests'], static fn (string $table): bool => ! $schema->hasTable($table)));
        } catch (Throwable $exception) {
            $problems[] = 'The settings tables could not be checked: '.$exception->getMessage();

            return [];
        }

        if ($missing !== []) {
            $problems[] = sprintf('The settings tables %s are missing: run the migrations (php artisan migrate).', implode(', ', $missing));

            return ['missing_tables' => $missing];
        }

        $invalid = $orphaned = $unencrypted = $disallowed = [];
        $examined = $this->container->make(SettingsRepository::class)->each(function (StoredValue $row) use ($definitions, &$invalid, &$orphaned, &$unencrypted, &$disallowed): void {
            $definition = $definitions[$row->key] ?? null;
            $where = $row->key.' @ '.$row->scope->value;

            if ($definition === null) {
                $orphaned[] = $where;

                return;
            }

            if (! $definition->allows($row->scope)) {
                $disallowed[] = $where;
            }

            if ($definition->sensitive && ! $row->encrypted) {
                $unencrypted[] = $where;
            }

            try {
                $this->reader->decode($definition, $row);
            } catch (InvalidSettingException $exception) {
                $invalid[] = $where.' ('.($exception->reason ?? 'invalid').')';
            }
        }, $limit);

        foreach (['invalid' => $invalid, 'unencrypted' => $unencrypted] as $kind => $rows) {
            if ($rows !== []) {
                $problems[] = sprintf('%d stored setting values are %s: %s.', count($rows), $kind === 'invalid' ? 'invalid for their definition' : 'sensitive but not encrypted', implode(', ', array_slice($rows, 0, 20)));
            }
        }

        foreach (['orphaned' => $orphaned, 'disallowed' => $disallowed] as $kind => $rows) {
            if ($rows !== []) {
                $warnings[] = sprintf('%d stored setting values are %s (they are ignored): %s.', count($rows), $kind === 'orphaned' ? 'of settings no longer registered' : 'at scopes their setting does not allow', implode(', ', array_slice($rows, 0, 20)));
            }
        }

        return [
            'stored' => SettingRecord::query()->count(),
            'examined' => $examined,
            'invalid' => $invalid,
            'orphaned' => $orphaned,
            'unencrypted' => $unencrypted,
            'disallowed' => $disallowed,
            'pending_requests' => SettingRequest::query()->where('status', SettingRequest::Pending)->count(),
        ];
    }

    private function workflowExists(string $workflow): bool
    {
        try {
            return $this->container->bound(WorkflowDefinitions::class) && $this->container->make(WorkflowDefinitions::class)->has($workflow);
        } catch (Throwable) {
            return false;
        }
    }

    private function livewireHas(string $component): bool
    {
        try {
            $finder = $this->container->make('livewire.finder');

            return is_object($finder) && method_exists($finder, 'resolveClassComponentClassName') && $finder->resolveClassComponentClassName($component) !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
