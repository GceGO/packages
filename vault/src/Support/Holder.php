<?php

declare(strict_types=1);

namespace Gcego\Vault\Support;

use Gcego\Vault\Exceptions\VaultValidationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The stored identity of anything that holds roles or acts on Vault: the
 * model's morph class and its key as a string (integer, ULID and UUID keys).
 */
final readonly class Holder
{
    public function __construct(public string $type, public string $id, public ?string $name = null) {}

    public static function of(Model $model): self
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key) || (string) $key === '') {
            throw VaultValidationException::make('unsaved_holder');
        }

        $name = $model->getAttribute('name');

        return new self($model->getMorphClass(), (string) $key, is_string($name) ? mb_substr($name, 0, 100) : null);
    }

    public static function ofActor(?Authenticatable $actor): ?self
    {
        return $actor instanceof Model ? self::of($actor) : null;
    }

    public function is(?self $other): bool
    {
        return $other !== null && $other->type === $this->type && $other->id === $this->id;
    }

    public function isModel(Model $model): bool
    {
        return $this->is(self::of($model));
    }

    public function key(): string
    {
        return $this->type.'#'.$this->id;
    }
}
