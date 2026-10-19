<?php

declare(strict_types=1);

namespace Gcego\Settings\Types;

use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Exceptions\InvalidSettingException;
use JsonException;

/**
 * Typed values to stored text and back, as JSON only: never PHP
 * serialize()/unserialize(), so stored data can never instantiate
 * objects. Reading casts the decoded value strictly again (SettingCaster),
 * so a stored value that no longer fits its definition is reported
 * (InvalidSettingException), never returned in the wrong type.
 *
 * An explicitly stored null is the JSON text "null": it is a value, unlike
 * a missing row.
 */
final readonly class SettingSerializer
{
    public function __construct(private SettingCaster $caster = new SettingCaster) {}

    public function serialize(SettingDefinition $definition, mixed $value): string
    {
        try {
            return json_encode($this->caster->plain($definition->type, $value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw InvalidSettingException::cast('json');
        }
    }

    /**
     * @throws InvalidSettingException when the stored text does not decode to a value of the setting's type
     */
    public function unserialize(SettingDefinition $definition, string $payload): mixed
    {
        try {
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw InvalidSettingException::cast('json');
        }

        return $this->caster->cast($definition->type, $definition->type === SettingType::Json && is_string($decoded) ? json_encode($decoded) : $decoded, $definition->nullable, $definition->enum);
    }
}
