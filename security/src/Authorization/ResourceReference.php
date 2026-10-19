<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * A safe description of what was checked: a type and, for records, the key. Never attributes.
 */
final readonly class ResourceReference
{
    public function __construct(
        public ?string $type,
        public string|int|null $key = null,
    ) {}

    public static function of(mixed $resource): self
    {
        return match (true) {
            $resource === null => new self(null),
            $resource instanceof Model => new self($resource::class, self::key($resource->getKey())),
            is_string($resource) && (class_exists($resource) || interface_exists($resource)) => new self($resource),
            is_object($resource) => new self($resource::class),
            is_array($resource) => new self('array', isset($resource['id']) ? self::key($resource['id']) : null),
            default => new self(get_debug_type($resource)),
        };
    }

    public function toString(): string
    {
        return $this->type === null ? '' : $this->type.($this->key === null ? '' : '#'.$this->key);
    }

    private static function key(mixed $key): string|int|null
    {
        return match (true) {
            is_int($key), is_string($key) => $key,
            $key instanceof BackedEnum => $key->value,
            $key instanceof UnitEnum => $key->name,
            default => null,
        };
    }
}
