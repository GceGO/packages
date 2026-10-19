<?php

declare(strict_types=1);

namespace Gcego\Settings\Resolution;

use Gcego\Settings\Cache\SettingsCache;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Persistence\StoredValue;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Scopes\ScopePrecedenceResolver;
use Gcego\Settings\Security\SettingEncryption;
use Gcego\Settings\Support\SettingsLogger;
use Gcego\Settings\Types\SettingSerializer;

/**
 * Effective values: for each setting, the first level of its precedence
 * that stores a valid value, else the definition default. All the scope
 * instances a batch needs are loaded together (cache, else one query).
 *
 * A stored value that no longer decodes (its definition changed type, or
 * it was encrypted with a key that is gone) is skipped and logged, so the
 * next level applies; gcego:settings:inspect lists it. Values are
 * decrypted here only, for trusted server code.
 */
final readonly class SettingsReader
{
    public function __construct(
        private SettingsCache $cache,
        private ScopePrecedenceResolver $precedence,
        private SettingSerializer $serializer,
        private SettingEncryption $encryption,
        private SettingsLogger $logger,
    ) {}

    public function resolve(SettingDefinition $definition, ScopeContext $context): ResolvedSetting
    {
        return $this->resolveMany([$definition], $context)[$definition->key];
    }

    /**
     * @param  list<SettingDefinition>  $definitions
     * @param  bool  $skipTarget  Ignore what the target stores (the values a reset would leave).
     * @return array<string, ResolvedSetting>
     */
    public function resolveMany(array $definitions, ScopeContext $context, bool $skipTarget = false): array
    {
        $instances = [];
        $target = $context->targetKey();

        if ($target !== null) {
            $instances[$context->target->value.'|'.$target] = ['scope' => $context->target, 'key' => $target];
        }

        $candidates = [];

        foreach ($definitions as $definition) {
            $candidates[$definition->key] = $this->precedence->candidates($definition, $context);

            foreach ($candidates[$definition->key] as $candidate) {
                $instances[$candidate['scope']->value.'|'.$candidate['key']] = $candidate;
            }
        }

        $stored = $this->cache->scopes(array_values($instances));
        $resolved = [];

        foreach ($definitions as $definition) {
            $value = $definition->default;
            $source = null;

            foreach ($candidates[$definition->key] as $candidate) {
                if ($skipTarget && $candidate['scope'] === $context->target) {
                    continue;
                }

                $row = $stored[$candidate['scope']->value.'|'.$candidate['key']][$definition->key] ?? null;

                if ($row === null) {
                    continue;
                }

                try {
                    $value = $this->decode($definition, $row);
                    $source = $candidate['scope'];

                    break;
                } catch (InvalidSettingException $exception) {
                    $this->logger->warning('invalid_value', ['key' => $definition->key, 'scope' => $candidate['scope']->value, 'reason' => $exception->reason]);
                }
            }

            $version = $target === null ? 0 : ($stored[$context->target->value.'|'.$target][$definition->key] ?? null)->version ?? 0;
            $resolved[$definition->key] = new ResolvedSetting($definition->key, $value, $context->target, $source, $version, $definition->sensitive);
        }

        return $resolved;
    }

    /**
     * The typed value of a stored row.
     *
     * @throws InvalidSettingException
     */
    public function decode(SettingDefinition $definition, StoredValue $row): mixed
    {
        $payload = $row->encrypted ? $this->encryption->decrypt($row->payload) : $row->payload;

        return $this->serializer->unserialize($definition, $payload);
    }
}
