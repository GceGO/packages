<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Forms\Building\FieldBuilder;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Forms\Contracts\FormComponent;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\State\StatePath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The base of every field: a declarative definition, never a value holder.
 *
 *     new TextInput('name', 'Subject Name')
 *     new TextInput(new Property(TextInput::Name, 'name'), new Property(TextInput::Required, true))
 *
 * Common properties live here; each field type adds only its own. Field
 * types translate their properties into Laravel validation rules
 * (typeRules()) and convert values between the browser, the form state and
 * storage (normalizeInput(), hydrate(), dehydrate()).
 */
abstract class Field extends Component implements FormComponent
{
    public const string Placeholder = 'placeholder';

    /** Initial value when there is no record value. */
    public const string Default = 'default';

    public const string Required = 'required';

    /** Disabled and read-only fields are rendered, never written: their submitted values are ignored. */
    public const string Disabled = 'disabled';

    public const string Readonly = 'readonly';

    /** Not rendered, not validated, not written. */
    public const string Hidden = 'hidden';

    public const string HelperText = 'helper_text';

    public const string Hint = 'hint';

    /** false: the value is validated but never part of the submitted data. */
    public const string Dehydrated = 'dehydrated';

    /** Send changes to the server immediately (fields others depend on are live automatically). */
    public const string Live = 'live';

    /** Columns spanned inside a Grid (1 to 4). */
    public const string ColumnSpan = 'column_span';

    public const string Autofocus = 'autofocus';

    /** Styling slots defined by forms (Tailwind::Wrapper and Tailwind::Label apply too). */
    public const string Input = 'input';

    public const string Helper = 'helper';

    public const string Error = 'error';

    public function fieldName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : Str::headline($this->fieldName());
    }

    public function isRequired(): bool
    {
        return $this->get(self::Required) === true;
    }

    public function isDisabled(): bool
    {
        return $this->get(self::Disabled) === true;
    }

    public function isReadonly(): bool
    {
        return $this->get(self::Readonly) === true;
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    public function isDehydrated(): bool
    {
        return $this->get(self::Dehydrated) !== false;
    }

    public function isLive(): bool
    {
        return $this->get(self::Live) === true;
    }

    public function columnSpan(): int
    {
        $span = $this->get(self::ColumnSpan);

        return is_int($span) ? max(1, min(4, $span)) : 1;
    }

    /**
     * The Blade view of this field ("gcego-forms::fields.text-input").
     */
    abstract public function view(): string;

    /**
     * Rules implied by the field type and its properties (required/nullable is added by the validation engine).
     *
     * @return list<mixed>
     */
    public function typeRules(): array
    {
        return [];
    }

    /**
     * Whether submitted values are accepted at all (Hidden fields only keep server values).
     */
    public function acceptsSubmission(): bool
    {
        return true;
    }

    /**
     * Whether an empty submission means "leave unchanged" (passwords).
     */
    public function skipsEmpty(): bool
    {
        return false;
    }

    /**
     * Submitted (untrusted) value to the form's typed state, before validation.
     */
    public function normalizeInput(mixed $value, FormsConfig $config): mixed
    {
        if (is_string($value)) {
            $value = $config->trimStrings ? trim($value) : $value;

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * The record's current value of this field (what edit forms hydrate and
     * what an upload may keep). Fields that are not columns override it.
     */
    public function currentValue(Model $record): mixed
    {
        return $record->getAttribute($this->fieldName());
    }

    /**
     * A trusted record value to form state.
     */
    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * A validated value to the value written to storage.
     */
    public function dehydrate(mixed $value, FormsConfig $config): mixed
    {
        return $value;
    }

    public function emptyState(): mixed
    {
        return $this->get(self::Default);
    }

    public static function builderClass(): string
    {
        return FieldBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('forms.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id, self::Description)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(StatePath::NamePattern, $value) === 1, ruleDescription: 'a field name (letters, digits and "_")'),
                new PropertyDefinition(self::Placeholder, Type::string()),
                new PropertyDefinition(self::Default, Type::mixed(), nullable: true),
                new PropertyDefinition(self::Required, Type::bool(), default: false),
                new PropertyDefinition(self::Disabled, Type::bool(), default: false),
                new PropertyDefinition(self::Readonly, Type::bool(), default: false),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
                new PropertyDefinition(self::HelperText, Type::string()),
                new PropertyDefinition(self::Hint, Type::string()),
                new PropertyDefinition(self::Dehydrated, Type::bool(), default: true),
                new PropertyDefinition(self::Live, Type::bool(), default: false),
                new PropertyDefinition(self::ColumnSpan, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'),
                new PropertyDefinition(self::Autofocus, Type::bool(), default: false),
            )
            ->withoutChildren();
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }

    protected function intProperty(string $key): ?int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : null;
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : null;
    }
}
