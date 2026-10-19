<?php

declare(strict_types=1);

namespace Gcego\Settings\Types;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Gcego\Settings\Exceptions\InvalidSettingException;
use JsonException;
use Throwable;

/**
 * Converts values to a setting's declared type, strictly. A conversion
 * happens only when it is exact; anything else is refused with a reason,
 * never silently coerced:
 *
 * - String:   strings; integers and finite floats become their decimal text; booleans and arrays are refused.
 * - Integer:  integers, digit strings ("-12") in range, and floats without a fraction (3.0); "3.5", 3.5 and "abc" are refused.
 * - Float:    integers, finite floats and numeric strings; NAN and INF are refused.
 * - Boolean:  booleans, 1/0, and "true"/"false", "1"/"0", "yes"/"no", "on"/"off" (any case); "2" and "maybe" are refused.
 * - Array:    arrays, and JSON text whose top level is an array or object.
 * - Json:     arrays, scalars and null; strings are parsed as JSON text and must be valid.
 * - Date:     DateTimeInterface or "Y-m-d" (a real calendar date), as a CarbonImmutable at midnight UTC.
 * - DateTime: DateTimeInterface or an ISO 8601 / "Y-m-d H:i[:s]" string, as a CarbonImmutable in UTC.
 * - Enum:     a case of the declared enum, or one of its backing values ("5" for an int-backed enum).
 * - Media:    a gcego/media ULID (26 Crockford base32 characters, stored lowercase like gcego/media); ownership is validated separately.
 *
 * Arrays may only contain null, scalars and arrays (at most 16 levels).
 * Objects are never deserialized from stored data: only JSON is decoded.
 */
final class SettingCaster
{
    public const string UlidPattern = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private const int MaxDepth = 16;

    /**
     * @param  class-string<BackedEnum>|null  $enum
     *
     * @throws InvalidSettingException with a reason code (InvalidSettingException::$reason, e.g. "integer")
     */
    public function cast(SettingType $type, mixed $value, bool $nullable, ?string $enum = null): mixed
    {
        if ($value === null) {
            return $nullable ? null : throw self::refuse('empty');
        }

        return match ($type) {
            SettingType::String => $this->string($value),
            SettingType::Integer => $this->integer($value),
            SettingType::Float => $this->float($value),
            SettingType::Boolean => $this->boolean($value),
            SettingType::Array => $this->array($value),
            SettingType::Json => $this->json($value),
            SettingType::Date => $this->date($value),
            SettingType::DateTime => $this->dateTime($value),
            SettingType::Enum => $this->enum($value, $enum),
            SettingType::Media => $this->media($value),
        };
    }

    /**
     * The JSON-safe form of a typed value (what is stored, cached, exported and shown in history).
     */
    public function plain(SettingType $type, mixed $value): mixed
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $type === SettingType::Date
                ? $value->format('Y-m-d')
                : CarbonImmutable::instance($value)->utc()->format(DateTimeInterface::ATOM),
            default => $value,
        };
    }

    /**
     * Strict equality of two typed values of the same type.
     */
    public function same(SettingType $type, mixed $left, mixed $right): bool
    {
        return $this->plain($type, $left) === $this->plain($type, $right);
    }

    private function string(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_float($value) && is_finite($value) => (string) $value,
            default => throw self::refuse('text'),
        };
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) <= PHP_INT_MAX) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d{1,19}$/', trim($value)) === 1) {
            $integer = filter_var(trim($value), FILTER_VALIDATE_INT);

            if (is_int($integer)) {
                return $integer;
            }
        }

        throw self::refuse('integer');
    }

    private function float(mixed $value): float
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value)) && is_finite((float) trim($value))) {
            return (float) trim($value);
        }

        throw self::refuse('number');
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 0) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => throw self::refuse('boolean'),
            };
        }

        throw self::refuse('boolean');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function array(mixed $value): array
    {
        if (is_string($value)) {
            $value = $this->decode($value);

            if (! is_array($value)) {
                throw self::refuse('array');
            }
        }

        if (! is_array($value)) {
            throw self::refuse('array');
        }

        $this->assertPlain($value, 0);

        return $value;
    }

    private function json(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = $this->decode($value);
        }

        if (is_float($value) && ! is_finite($value)) {
            throw self::refuse('json');
        }

        if (is_array($value)) {
            $this->assertPlain($value, 0);
        } elseif ($value !== null && ! is_scalar($value)) {
            throw self::refuse('json');
        }

        return $value;
    }

    private function date(mixed $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value->format('Y-m-d'), 'UTC') ?: throw self::refuse('date');
        }

        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $parts) === 1 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', trim($value), 'UTC');

            if ($date instanceof CarbonImmutable) {
                return $date;
            }
        }

        throw self::refuse('date');
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (is_string($value)) {
            $text = trim($value);

            foreach ([DateTimeInterface::ATOM, 'Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
                try {
                    $parsed = CarbonImmutable::createFromFormat('!'.$format, $text, new DateTimeZone('UTC'));
                } catch (Throwable) {
                    continue;
                }

                $errors = CarbonImmutable::getLastErrors();

                if ($parsed instanceof CarbonImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return $parsed->utc();
                }
            }
        }

        throw self::refuse('datetime');
    }

    /**
     * @param  class-string<BackedEnum>|null  $enum
     */
    private function enum(mixed $value, ?string $enum): BackedEnum
    {
        if ($enum === null || ! enum_exists($enum)) {
            throw self::refuse('enum');
        }

        if ($value instanceof $enum) {
            return $value;
        }

        $backing = (new \ReflectionEnum($enum))->getBackingType()?->getName();

        if ($backing === 'int' && is_string($value) && preg_match('/^-?\d{1,19}$/', $value) === 1) {
            $value = (int) $value;
        }

        if (($backing === 'int' && is_int($value)) || ($backing === 'string' && is_string($value))) {
            $case = $enum::tryFrom($value);

            if ($case !== null) {
                return $case;
            }
        }

        throw self::refuse('enum');
    }

    private function media(mixed $value): string
    {
        if (is_string($value) && preg_match(self::UlidPattern, trim($value)) === 1) {
            return strtolower(trim($value));
        }

        throw self::refuse('media');
    }

    private function decode(string $text): mixed
    {
        if (strlen($text) > 1_000_000) {
            throw self::refuse('large');
        }

        try {
            return json_decode($text, true, self::MaxDepth + 1, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw self::refuse('json');
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function assertPlain(array $value, int $depth): void
    {
        if ($depth >= self::MaxDepth) {
            throw self::refuse('deep');
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                $this->assertPlain($item, $depth + 1);
            } elseif (($item !== null && ! is_scalar($item)) || (is_float($item) && ! is_finite($item))) {
                throw self::refuse('plain');
            }
        }
    }

    private static function refuse(string $reason): InvalidSettingException
    {
        return InvalidSettingException::cast($reason);
    }
}
