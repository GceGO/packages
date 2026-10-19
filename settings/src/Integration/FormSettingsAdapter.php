<?php

declare(strict_types=1);

namespace Gcego\Settings\Integration;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Gcego\Components\Property;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Fields\Checkbox;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\DateTimePicker;
use Gcego\Forms\Fields\EmailInput;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\NumberInput;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Radio;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\Textarea;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\Toggle;
use Gcego\Forms\Form;
use Gcego\Forms\FormsConfig;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Pages\SettingsScreen;
use Gcego\Settings\Resolution\ResolvedSetting;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingType;
use JsonException;

/**
 * Settings definitions as gcego/forms fields (the forms engine renders,
 * normalizes and validates them; nothing is re-implemented here):
 *
 * | Field    | gcego/forms     | Field    | gcego/forms       |
 * | -------- | --------------- | -------- | ----------------- |
 * | text     | TextInput       | select   | Select + Options  |
 * | email    | EmailInput      | radio    | Radio + Options   |
 * | url      | TextInput (url) | date     | DatePicker        |
 * | textarea | Textarea        | datetime | DateTimePicker    |
 * | number   | NumberInput     | json     | Textarea (JSON)   |
 * | toggle   | Toggle          | media    | TextInput (ULID)  |
 * | checkbox | Checkbox        | password | PasswordInput     |
 *
 * Fields are named after setting keys. Fields the viewer may not change
 * are disabled (gcego/forms then ignores what the browser sends for them).
 * Sensitive settings always use a PasswordInput: never hydrated, an empty
 * submission changes nothing.
 */
final readonly class FormSettingsAdapter
{
    public function __construct(private FormsConfig $config, private SettingCaster $caster = new SettingCaster) {}

    /**
     * The whole screen as one form (for normalizing and validating a submission).
     */
    public function form(SettingsScreen $screen): Form
    {
        return new Form(new Property(Form::Name, 'settings'), ...array_map(fn (SettingDefinition $definition): Field => $this->field($definition, $screen->canEdit($definition->key), false), $screen->visible));
    }

    /**
     * One setting as a single-field form (rendered row by row, next to its scope indicators).
     */
    public function single(SettingDefinition $definition, bool $editable, bool $hasSecret): Form
    {
        return new Form(new Property(Form::Name, 'settings'), $this->field($definition, $editable, $hasSecret));
    }

    public function field(SettingDefinition $definition, bool $editable, bool $hasSecret): Field
    {
        $properties = array_values(array_filter([
            $definition->description() === null ? null : new Property(Field::HelperText, $definition->description()),
            $definition->setting->placeholder() === null ? null : new Property(Field::Placeholder, $definition->setting->placeholder()),
            $definition->required && ! $definition->sensitive && ! in_array($definition->field, ['toggle', 'checkbox'], true) ? new Property(Field::Required, true) : null,
            $editable ? null : new Property(Field::Disabled, true),
            $definition->setting->span() > 1 ? new Property(Field::ColumnSpan, $definition->setting->span()) : null,
        ]));

        if ($definition->sensitive) {
            $hint = $hasSecret ? 'gcego-settings::messages.secret_set' : 'gcego-settings::messages.secret_unset';

            return new PasswordInput($definition->key, $definition->label(), new Property(Field::Hint, $hint), new Property(PasswordInput::Revealable, false), ...$properties);
        }

        $options = $definition->type === SettingType::Enum && $definition->enum !== null ? new Options($definition->enum) : new Options($definition->options ?? []);

        return match ($definition->field) {
            'email' => new EmailInput($definition->key, $definition->label(), ...$properties),
            'url' => new TextInput($definition->key, $definition->label(), new Property(TextInput::Type, 'url'), ...$properties),
            'textarea' => new Textarea($definition->key, $definition->label(), ...$properties),
            'json' => new Textarea($definition->key, $definition->label(), new Property(Textarea::Rows, 6), ...$properties),
            'number' => new NumberInput($definition->key, $definition->label(), new Property(NumberInput::Integer, $definition->type === SettingType::Integer), new Property(NumberInput::Step, $definition->type === SettingType::Integer ? 1 : 'any'), ...$properties),
            'toggle' => new Toggle($definition->key, $definition->label(), ...$properties),
            'checkbox' => new Checkbox($definition->key, $definition->label(), ...$properties),
            'select' => new Select($definition->key, $definition->label(), $options, ...$properties),
            'radio' => new Radio($definition->key, $definition->label(), $options, ...$properties),
            'date' => new DatePicker($definition->key, $definition->label(), ...$properties),
            'datetime' => new DateTimePicker($definition->key, $definition->label(), ...$properties),
            'media' => new TextInput($definition->key, $definition->label(), new Property(Field::Hint, 'gcego-settings::messages.media_hint'), ...$properties),
            default => new TextInput($definition->key, $definition->label(), ...$properties),
        };
    }

    /**
     * Form state of an effective value (sensitive values: always empty).
     */
    public function hydrate(SettingDefinition $definition, ResolvedSetting $resolved): mixed
    {
        $value = $resolved->value;

        if ($definition->sensitive) {
            return null;
        }

        return match (true) {
            $value === null => in_array($definition->field, ['toggle', 'checkbox'], true) ? false : null,
            $definition->field === 'json' => $this->json($value),
            $definition->type === SettingType::DateTime && $value instanceof DateTimeInterface => (new DateTimePicker($definition->key))->hydrate($value, $this->config),
            default => $this->caster->plain($definition->type, $value),
        };
    }

    /**
     * A value gcego/forms dehydrated, as a setting value (dates and times
     * back from the application's storage zone; JSON text stays text for
     * the setting caster; empty text stays text).
     */
    public function value(SettingDefinition $definition, mixed $value): mixed
    {
        // gcego/forms submits an emptied text field as null; a text setting that may not be null is empty text.
        if ($value === null && $definition->type === SettingType::String && ! $definition->nullable) {
            return '';
        }

        if ($definition->type === SettingType::DateTime && is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value, is_string($zone = config('app.timezone')) ? $zone : 'UTC')->utc();
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            return '';
        }
    }
}
