<?php

declare(strict_types=1);

namespace Gcego\Vault\Support;

use Gcego\Vault\VaultConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * URL-safe references to holders in the Vault screens: "<subject index>-<key>"
 * ("0-15" is the first configured subject model with key 15). Only
 * configured subject models can be referenced, never an arbitrary class.
 */
final readonly class HolderReference
{
    public function __construct(private VaultConfig $config) {}

    public function encode(Model $model): ?string
    {
        foreach ($this->config->subjects as $index => $class) {
            $key = $model->getKey();

            if ($model instanceof $class && (is_int($key) || is_string($key)) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $key) === 1) {
                return $index.'-'.$key;
            }
        }

        return null;
    }

    public function decode(string $reference): ?Model
    {
        if (preg_match('/^([0-9]{1,2})-([A-Za-z0-9_-]{1,64})$/', $reference, $match) !== 1) {
            return null;
        }

        $class = $this->config->subjects[(int) $match[1]] ?? null;

        return $class === null ? null : $class::query()->whereKey($match[2])->first();
    }

    public function forHolder(Holder $holder): ?string
    {
        foreach ($this->config->subjects as $index => $class) {
            if ((new $class)->getMorphClass() === $holder->type) {
                return $index.'-'.$holder->id;
            }
        }

        return null;
    }
}
