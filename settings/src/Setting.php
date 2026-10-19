<?php

declare(strict_types=1);

namespace Gcego\Settings;

use BackedEnum;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Settings\Definitions\SettingsNode;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Types\SettingType;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One setting, declared once in PHP (inside a SettingsGroup):
 *
 *     new Setting('site_name', 'GCEGO')                                   // name and default
 *     new Setting(name: 'maintenance_mode', default: false, type: SettingType::Boolean)
 *     new Setting(name: 'external_api_secret', default: null, encrypted: true)
 *     new Setting(new Property(Setting::Name, 'site_name'), new Property(Setting::Default, 'GCEGO'))
 *
 * All three styles produce the same definition. The key (Name) is unique
 * across every group. Without a Type, the default decides it (false:
 * Boolean, 3: Integer, a string or no default: String). Without Nullable,
 * a setting is nullable when its default is null or absent.
 *
 * Scopes lists where overrides may be stored (default: the group's, else
 * global only): a tenant can never override a platform setting unless the
 * setting allows the tenant scope. Sensitive values (and Encrypted ones,
 * which are sensitive too) are encrypted at rest, masked in forms, left
 * out of history, events, caches in plaintext and exports.
 *
 * gcego/security requirements given as items (new Permission('...')) are
 * required, together with the group's, to change it. Construction never
 * queries or reads configuration.
 */
final class Setting extends SettingsNode
{
    public const string Default = 'default';

    /** A SettingType (or its value). */
    public const string Type = 'type';

    public const string Nullable = 'nullable';

    /** The value may not be empty ("required" validation). */
    public const string Required = 'required';

    /** The backed enum class of an Enum setting. */
    public const string Enum = 'enum';

    /** Allowed values: value => label (a select). */
    public const string Options = 'options';

    /** Extra Laravel validation rules: rule strings ("email", "max:120") or ValidationRule class names. */
    public const string Rules = 'rules';

    /** Scopes overrides may be stored at (SettingScope values). */
    public const string Scopes = 'scopes';

    /** Lookup order of the allowed scopes, most specific first (default: gcego-settings.scopes.precedence). */
    public const string Precedence = 'precedence';

    /** Masked and redacted everywhere, and encrypted at rest. */
    public const string Sensitive = 'sensitive';

    /** Encrypted at rest (implies Sensitive). */
    public const string Encrypted = 'encrypted';

    /** The form field: text, textarea, email, url, number, toggle, checkbox, select, radio, date, datetime, json, media or password. */
    public const string Field = 'field';

    public const string Placeholder = 'placeholder';

    /** Extra words settings search matches. */
    public const string Keywords = 'keywords';

    /** Required when other settings have these values: ['mail_enabled' => true]. */
    public const string RequiredWhen = 'required_when';

    /** May only be enabled (truthy) while these settings are enabled: ['mail_enabled']. */
    public const string Requires = 'requires';

    /** A gcego/workflows workflow that must approve changes made by people. */
    public const string Approval = 'approval';

    /** Not shown in settings forms (still readable and writable by code). */
    public const string Hidden = 'hidden';

    /** Columns spanned in a section grid (1 to 4). */
    public const string Span = 'span';

    public const string KeyPattern = '/^[a-z][a-z0-9_]{0,63}$/';

    public const array Fields = ['text', 'textarea', 'email', 'url', 'number', 'toggle', 'checkbox', 'select', 'radio', 'date', 'datetime', 'json', 'media', 'password'];

    public function key(): string
    {
        return $this->nodeName();
    }

    public function hasDefault(): bool
    {
        return $this->hasExplicit(self::Default);
    }

    /**
     * The declared default as written (Date and DateTime defaults are strings; see SettingDefinition for the typed value).
     */
    public function rawDefault(): mixed
    {
        return $this->get(self::Default);
    }

    public function settingType(): SettingType
    {
        $type = $this->get(self::Type);

        if ($type instanceof SettingType) {
            return $type;
        }

        if (is_string($type) && SettingType::tryFrom($type) !== null) {
            return SettingType::from($type);
        }

        return $this->enumClass() !== null ? SettingType::Enum : SettingType::infer($this->rawDefault());
    }

    public function isNullable(): bool
    {
        $nullable = $this->get(self::Nullable);

        return is_bool($nullable) ? $nullable : (! $this->hasDefault() || $this->rawDefault() === null);
    }

    public function isRequired(): bool
    {
        return $this->get(self::Required) === true;
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    public function enumClass(): ?string
    {
        $enum = $this->get(self::Enum);

        if (is_string($enum) && enum_exists($enum) && is_subclass_of($enum, BackedEnum::class)) {
            return $enum;
        }

        $default = $this->rawDefault();

        return $default instanceof BackedEnum ? $default::class : null;
    }

    /**
     * @return array<array-key, string>|null
     */
    public function options(): ?array
    {
        $options = $this->get(self::Options);

        if (! is_array($options)) {
            return null;
        }

        $labels = [];

        foreach ($options as $value => $label) {
            $labels[$value] = is_string($label) ? $label : (string) $value;
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return $this->strings(self::Rules);
    }

    /**
     * Declared scopes, or null to use the group's.
     *
     * @return list<SettingScope>|null
     */
    public function declaredScopes(): ?array
    {
        return $this->hasExplicit(self::Scopes) ? SettingScope::list($this->strings(self::Scopes)) : null;
    }

    /**
     * @return list<SettingScope>|null
     */
    public function declaredPrecedence(): ?array
    {
        return $this->hasExplicit(self::Precedence) ? SettingScope::list($this->strings(self::Precedence)) : null;
    }

    public function isEncrypted(): bool
    {
        return $this->get(self::Encrypted) === true;
    }

    public function isSensitive(): bool
    {
        return $this->isEncrypted() || $this->get(self::Sensitive) === true;
    }

    public function field(): string
    {
        return $this->text(self::Field) ?? $this->settingType()->defaultField($this->isSensitive(), $this->options() !== null);
    }

    public function placeholder(): ?string
    {
        return $this->text(self::Placeholder);
    }

    /**
     * @return list<string>
     */
    public function keywords(): array
    {
        return $this->strings(self::Keywords);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function requiredWhen(): array
    {
        $conditions = $this->get(self::RequiredWhen);
        $clean = [];

        foreach (is_array($conditions) ? $conditions : [] as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->strings(self::Requires);
    }

    public function approval(): ?string
    {
        return $this->text(self::Approval);
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    public function span(): int
    {
        return $this->integer(self::Span) ?? 1;
    }

    public static function validKey(mixed $value): bool
    {
        return is_string($value) && preg_match(self::KeyPattern, $value) === 1;
    }

    /**
     * Whether a rule entry is a trusted rule string or a ValidationRule class.
     */
    public static function validRule(mixed $rule): bool
    {
        if (! is_string($rule) || $rule === '' || str_contains($rule, '|')) {
            return false;
        }

        if (class_exists($rule)) {
            return is_subclass_of($rule, ValidationRule::class);
        }

        return preg_match('/^[a-z][a-z_]*(?::.{1,500})?$/s', $rule) === 1;
    }

    protected static function namedArguments(): array
    {
        return [
            ...parent::namedArguments(),
            'default' => self::Default, 'type' => self::Type, 'nullable' => self::Nullable, 'required' => self::Required,
            'enum' => self::Enum, 'options' => self::Options, 'rules' => self::Rules, 'scopes' => self::Scopes,
            'precedence' => self::Precedence, 'sensitive' => self::Sensitive, 'encrypted' => self::Encrypted,
            'field' => self::Field, 'placeholder' => self::Placeholder, 'keywords' => self::Keywords,
            'requiredWhen' => self::RequiredWhen, 'requires' => self::Requires, 'approval' => self::Approval,
            'hidden' => self::Hidden, 'span' => self::Span,
        ];
    }

    protected static function keepsNull(): array
    {
        return ['default'];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $scopes = static fn (mixed $value): bool => is_array($value) && $value !== [] && array_is_list($value)
            && array_filter($value, static fn (mixed $scope): bool => ! is_string($scope) || SettingScope::tryFrom($scope) === null) === []
            && count(array_unique(array_filter($value, is_string(...)))) === count($value);
        $strings = static fn (mixed $value): bool => is_array($value) && array_is_list($value) && array_filter($value, static fn (mixed $entry): bool => ! is_string($entry) || trim($entry) === '' || mb_strlen($entry) > 64) === [];
        $keys = static fn (mixed $value): bool => is_array($value) && array_is_list($value) && array_filter($value, static fn (mixed $key): bool => ! self::validKey($key)) === [];

        return parent::defineSchema()
            ->withType('settings.setting')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validKey(...), ruleDescription: 'a lowercase key such as "site_name" (letters, digits and "_", at most 64)'),
                new PropertyDefinition(self::Label, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120, ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::Default, Type::mixed(), nullable: true),
                new PropertyDefinition(self::Type, [Type::string(), Type::enum(SettingType::class)], allowedValues: [...array_map(static fn (SettingType $type): string => $type->value, SettingType::cases()), ...SettingType::cases()]),
                new PropertyDefinition(self::Nullable, Type::bool()),
                new PropertyDefinition(self::Required, Type::bool(), default: false),
                new PropertyDefinition(self::Enum, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && enum_exists($value) && is_subclass_of($value, BackedEnum::class), ruleDescription: 'a backed enum class'),
                new PropertyDefinition(self::Options, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $label): bool => ! is_string($label)) === [], ruleDescription: 'value => label pairs'),
                new PropertyDefinition(self::Rules, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $rule): bool => ! self::validRule($rule)) === [], ruleDescription: 'rule strings without "|", or ValidationRule class names'),
                new PropertyDefinition(self::Scopes, Type::list(), rule: $scopes, ruleDescription: 'scopes among global, panel, tenant and user'),
                new PropertyDefinition(self::Precedence, Type::list(), rule: $scopes, ruleDescription: 'scopes among global, panel, tenant and user'),
                new PropertyDefinition(self::Sensitive, Type::bool(), default: false),
                new PropertyDefinition(self::Encrypted, Type::bool(), default: false),
                new PropertyDefinition(self::Field, Type::string(), allowedValues: self::Fields),
                new PropertyDefinition(self::Placeholder, Type::string()),
                new PropertyDefinition(self::Keywords, Type::list(), rule: $strings, ruleDescription: 'a list of words'),
                new PropertyDefinition(self::RequiredWhen, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter(array_keys($value), static fn (mixed $key): bool => ! self::validKey($key)) === [] && array_filter($value, static fn (mixed $entry): bool => ! is_scalar($entry) && $entry !== null) === [], ruleDescription: 'setting key => scalar value pairs'),
                new PropertyDefinition(self::Requires, Type::list(), rule: $keys, ruleDescription: 'a list of setting keys'),
                new PropertyDefinition(self::Approval, Type::string(), rule: self::validName(...), ruleDescription: 'a workflow name'),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
                new PropertyDefinition(self::Span, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Default];
    }
}
