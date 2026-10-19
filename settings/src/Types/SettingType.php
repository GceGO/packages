<?php

declare(strict_types=1);

namespace Gcego\Settings\Types;

use UnitEnum;

/**
 * The declared type of a setting value, and what get() returns for it:
 *
 * | Type     | PHP value                       | Stored as (JSON)          |
 * | -------- | ------------------------------- | ------------------------- |
 * | String   | string                          | string                    |
 * | Integer  | int                             | number                    |
 * | Float    | float                           | number                    |
 * | Boolean  | bool                            | true / false              |
 * | Array    | array                           | array or object           |
 * | Json     | null, scalar or array           | any JSON value            |
 * | Date     | CarbonImmutable (start of day)  | "2026-10-17"              |
 * | DateTime | CarbonImmutable (UTC)           | "2026-10-17T08:30:00+00:00" |
 * | Enum     | the BackedEnum case             | its backing value         |
 * | Media    | string (a gcego/media ULID)     | string                    |
 *
 * Nullability is declared separately (Setting::Nullable).
 */
enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Array = 'array';
    case Json = 'json';
    case Date = 'date';
    case DateTime = 'datetime';
    case Enum = 'enum';
    case Media = 'media';

    /**
     * The type a default value implies when no Type is declared.
     */
    public static function infer(mixed $default): self
    {
        return match (true) {
            $default instanceof UnitEnum => self::Enum,
            is_bool($default) => self::Boolean,
            is_int($default) => self::Integer,
            is_float($default) => self::Float,
            is_array($default) => self::Array,
            default => self::String,
        };
    }

    /**
     * The form field used when the setting declares none.
     */
    public function defaultField(bool $sensitive, bool $hasOptions): string
    {
        if ($sensitive) {
            return 'password';
        }

        return match ($this) {
            self::Boolean => 'toggle',
            self::Integer, self::Float => $hasOptions ? 'select' : 'number',
            self::Enum => 'select',
            self::Array, self::Json => 'json',
            self::Date => 'date',
            self::DateTime => 'datetime',
            self::Media => 'media',
            self::String => $hasOptions ? 'select' : 'text',
        };
    }

    /**
     * Form fields that can edit this type.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::String => ['text', 'textarea', 'email', 'url', 'select', 'radio', 'password'],
            self::Integer, self::Float => ['number', 'select', 'radio'],
            self::Boolean => ['toggle', 'checkbox'],
            self::Array => ['json'],
            self::Json => ['json'],
            self::Date => ['date'],
            self::DateTime => ['datetime'],
            self::Enum => ['select', 'radio'],
            self::Media => ['media'],
        };
    }
}
