<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Support\Absent;
use Gcego\Components\Type;

/**
 * A single-line text control:
 *
 *     new TextInput('code', 'Subject Code', new Property(TextInput::MaxLength, 20), new Property(TextInput::Pattern, '[A-Z]{3}[0-9]{3}'))
 *     new TextInput('website', 'Website', new Property(TextInput::Type, 'url'))
 *
 * Type: text, email, url, tel or search (passwords: PasswordInput).
 */
class TextInput extends Field
{
    public const string Type = 'type';

    public const string MinLength = 'min_length';

    public const string MaxLength = 'max_length';

    /** A regular expression the whole value must match, without delimiters ("[A-Z]{3}"). */
    public const string Pattern = 'pattern';

    public const string Autocomplete = 'autocomplete';

    public const string Prefix = 'prefix';

    public const string Suffix = 'suffix';

    public const array Types = ['text', 'email', 'url', 'tel', 'search', 'password'];

    public function view(): string
    {
        return 'gcego-forms::fields.text-input';
    }

    public function inputType(): string
    {
        $type = $this->get(self::Type);

        return is_string($type) && in_array($type, self::Types, true) ? $type : 'text';
    }

    public function typeRules(): array
    {
        $rules = ['string'];
        $min = $this->intProperty(self::MinLength);
        $max = $this->intProperty(self::MaxLength);
        $pattern = $this->stringProperty(self::Pattern);

        if ($min !== null) {
            $rules[] = 'min:'.$min;
        }

        if ($max !== null) {
            $rules[] = 'max:'.$max;
        }

        $rules = [...$rules, ...match ($this->inputType()) {
            'email' => ['email'],
            'url' => ['url'],
            default => [],
        }];

        if ($pattern !== null) {
            $rules[] = 'regex:'.self::delimited($pattern);
        }

        return $rules;
    }

    public static function delimited(string $pattern): string
    {
        return '/^(?:'.str_replace('/', '\/', $pattern).')$/u';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Type, Type::string(), default: static::defaultType(), allowedValues: self::Types),
            new PropertyDefinition(self::MinLength, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'a non-negative length'),
            new PropertyDefinition(self::MaxLength, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1, ruleDescription: 'a positive length'),
            new PropertyDefinition(self::Pattern, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && @preg_match(self::delimited($value), '') !== false, ruleDescription: 'a valid regular expression'),
            new PropertyDefinition(self::Autocomplete, Type::string(), default: static::defaultAutocomplete() ?? Absent::Value, rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z0-9 -]{1,60}$/', $value) === 1, ruleDescription: 'an autocomplete token'),
            new PropertyDefinition(self::Prefix, Type::string()),
            new PropertyDefinition(self::Suffix, Type::string()),
        );
    }

    protected static function defaultType(): string
    {
        return 'text';
    }

    protected static function defaultAutocomplete(): ?string
    {
        return null;
    }
}
