<?php

declare(strict_types=1);

namespace Gcego\Testing;

/**
 * The validated gcego-testing configuration.
 */
final readonly class TestingConfig
{
    /**
     * @param  list<string>  $redact
     * @param  array{reset_registry: bool, reset_runtime: bool, reset_tenant_context: bool}  $isolation
     */
    public function __construct(
        public bool $enabled = true,
        public bool $verboseFailures = true,
        public array $redact = ['*password*', '*secret*', '*token*', '*api_key*', '*apikey*', '*authorization*', '*cookie*', '*credential*', '*private_key*', 'remember_token', 'two_factor_*'],
        public ?string $fixturesDirectory = null,
        public ?string $userProvider = null,
        public ?string $tenantProvider = null,
        public int $repeatedQueries = 5,
        public string $node = 'node',
        public ?string $nodeModules = null,
        public array $isolation = ['reset_registry' => true, 'reset_runtime' => true, 'reset_tenant_context' => true],
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $defaults = new self;
        $get = static fn (string $path, mixed $default): mixed => data_get($config, $path, $default);
        $string = static fn (string $path): ?string => is_string($value = $get($path, null)) && $value !== '' ? $value : null;
        $redact = $get('assertions.redact', $defaults->redact);
        $isolation = [];

        foreach ($defaults->isolation as $key => $default) {
            $isolation[$key] = (bool) $get('isolation.'.$key, $default);
        }

        return new self(
            enabled: (bool) $get('enabled', true),
            verboseFailures: (bool) $get('assertions.verbose_failures', true),
            redact: is_array($redact) ? array_values(array_filter($redact, is_string(...))) : $defaults->redact,
            fixturesDirectory: $string('fixtures.directory'),
            userProvider: $string('fixtures.users'),
            tenantProvider: $string('fixtures.tenants'),
            repeatedQueries: max(2, is_numeric($repeated = $get('performance.repeated_queries', 5)) ? (int) $repeated : 5),
            node: $string('accessibility.node') ?? 'node',
            nodeModules: $string('accessibility.node_modules'),
            isolation: $isolation,
        );
    }
}
