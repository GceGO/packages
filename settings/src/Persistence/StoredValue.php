<?php

declare(strict_types=1);

namespace Gcego\Settings\Persistence;

use Gcego\Settings\Scopes\SettingScope;

/**
 * A stored row as plain data (what the repository returns and the cache
 * keeps): the payload stays encrypted when the setting is sensitive.
 */
final readonly class StoredValue
{
    public function __construct(
        public string $key,
        public SettingScope $scope,
        public string $scopeKey,
        public string $payload,
        public string $type,
        public bool $encrypted,
        public int $version,
    ) {}

    public static function fromRecord(SettingRecord $record): self
    {
        return new self($record->key, SettingScope::from($record->scope), $record->scope_key, $record->value, $record->type, $record->encrypted, $record->version);
    }

    /**
     * @return array{p: string, t: string, e: bool, v: int}
     */
    public function toCache(): array
    {
        return ['p' => $this->payload, 't' => $this->type, 'e' => $this->encrypted, 'v' => $this->version];
    }

    public static function fromCache(string $key, SettingScope $scope, string $scopeKey, mixed $entry): ?self
    {
        if (! is_array($entry) || ! is_string($entry['p'] ?? null) || ! is_string($entry['t'] ?? null) || ! is_bool($entry['e'] ?? null) || ! is_int($entry['v'] ?? null)) {
            return null;
        }

        return new self($key, $scope, $scopeKey, $entry['p'], $entry['t'], $entry['e'], $entry['v']);
    }
}
