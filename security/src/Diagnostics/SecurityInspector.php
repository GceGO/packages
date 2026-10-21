<?php

declare(strict_types=1);

namespace Gcego\Security\Diagnostics;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\Contracts\DataAuthorizer;
use Gcego\Data\Source;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Integration\SecurityDataAuthorizer;
use Gcego\Security\Integration\SecurityRenderAuthorizer;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Gcego\Security\SecurityConfig;
use Illuminate\Auth\Access\Gate as LaravelGate;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Finds security gaps without granting anything:
 *
 * - config / tenancy: the active configuration and tenancy setup;
 * - integration: whether runtime and data use the security authorizers;
 * - gate: Gate::before rules (they affect Gate abilities and global permissions);
 * - definitions: registered definitions without requirements (denied, so unusable),
 *   and data sources whose model has no policy and no requirement;
 * - definition: loading failures (invalid permission names surface here).
 */
final readonly class SecurityInspector
{
    public function __construct(
        private Container $container,
        private SecurityConfig $config,
        private Gate $gate,
        private ?RegistryInterface $registry = null,
    ) {}

    public function inspect(): SecurityReport
    {
        $diagnostics = [
            new SecurityDiagnostic('config', SecurityDiagnostic::Ok, 'Authorization denies by default.', [
                'denial_status' => $this->config->denialStrategy->value,
                'permissions' => $this->config->permissionProvider,
                'roles' => $this->config->roleProvider,
                'audit' => $this->config->audit,
            ]),
            $this->tenancy(),
            ...$this->integration(),
            ...$this->gateRules(),
        ];

        foreach ($this->registry?->descriptors() ?? [] as $descriptor) {
            try {
                $definition = $this->registry?->get($descriptor->key());
            } catch (Throwable $exception) {
                $diagnostics[] = new SecurityDiagnostic('definition', SecurityDiagnostic::Error, sprintf('%s could not be loaded: %s', $descriptor->key(), $exception->getMessage()), ['definition' => $descriptor->key()]);

                continue;
            }

            if ($definition instanceof ComponentInterface) {
                array_push($diagnostics, ...$this->definition($descriptor->key(), $definition));
            }
        }

        return new SecurityReport($diagnostics);
    }

    /**
     * @return list<SecurityDiagnostic>
     */
    public function definition(string $identity, ComponentInterface $definition): array
    {
        $requirements = $this->container->make(DefinitionAuthorizer::class)->requirements($definition) ?? [];
        $diagnostics = [$requirements === []
            ? new SecurityDiagnostic('definitions', SecurityDiagnostic::Error, sprintf('%s declares no authorization requirement: it is denied to everyone. Add a Permission, ResourceAccess, Ability or PublicAccess node.', $identity), ['definition' => $identity])
            : new SecurityDiagnostic('definitions', SecurityDiagnostic::Ok, sprintf('%s is protected by: %s.', $identity, implode('; ', array_map(static fn (Requirement $requirement): string => $requirement->describe(), $requirements))), ['definition' => $identity])];

        foreach (app(ComponentWalker::class)->preOrder($definition) as $node) {
            $source = $node->component;

            if (! $source instanceof Source || ! is_string($model = $source->get(Source::Model))) {
                continue;
            }

            $guarded = array_filter($source->configurations()->all(), static fn (mixed $configuration): bool => $configuration instanceof Requirement) !== [];

            if (! $guarded && $this->gate->getPolicyFor($model) === null) {
                $diagnostics[] = new SecurityDiagnostic('definitions', SecurityDiagnostic::Warning, sprintf('The %s source in %s has no policy and no requirement: every read is denied.', class_basename($model), $identity), ['definition' => $identity, 'model' => $model]);
            }
        }

        return $diagnostics;
    }

    private function tenancy(): SecurityDiagnostic
    {
        if (! $this->config->tenancy) {
            return $this->config->tenantModels === []
                ? new SecurityDiagnostic('tenancy', SecurityDiagnostic::Ok, 'Tenancy is disabled.')
                : new SecurityDiagnostic('tenancy', SecurityDiagnostic::Warning, 'tenancy.models is configured while tenancy is disabled: those sources return no rows.', ['models' => array_keys($this->config->tenantModels)]);
        }

        return new SecurityDiagnostic('tenancy', SecurityDiagnostic::Ok, sprintf('Tenants are resolved by %s.', $this->config->tenantResolver), ['models' => array_keys($this->config->tenantModels)]);
    }

    /**
     * @return list<SecurityDiagnostic>
     */
    private function integration(): array
    {
        $diagnostics = [];

        foreach ([RenderAuthorizer::class => SecurityRenderAuthorizer::class, DataAuthorizer::class => SecurityDataAuthorizer::class] as $contract => $expected) {
            $bound = $this->container->bound($contract) ? $this->container->make($contract) : null;
            $diagnostics[] = $bound instanceof $expected
                ? new SecurityDiagnostic('integration', SecurityDiagnostic::Ok, sprintf('%s uses gcego/security.', class_basename($contract)))
                : new SecurityDiagnostic('integration', SecurityDiagnostic::Warning, sprintf('%s is bound to %s, not gcego/security: definition requirements are not enforced there.', class_basename($contract), $bound === null ? 'nothing' : $bound::class), ['contract' => $contract]);
        }

        return $diagnostics;
    }

    /**
     * @return list<SecurityDiagnostic>
     */
    private function gateRules(): array
    {
        if (! $this->gate instanceof LaravelGate) {
            return [];
        }

        $before = (fn (): int => count($this->beforeCallbacks))->call($this->gate);

        return $before === 0 ? [] : [new SecurityDiagnostic('gate', SecurityDiagnostic::Warning, sprintf('%d Gate::before rule(s) are registered. They can grant Gate abilities and global permissions; tenant permissions and record scopes are unaffected.', $before), ['before_callbacks' => $before])];
    }
}
