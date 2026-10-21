<?php

declare(strict_types=1);

namespace Gcego\Forms\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Forms\Configuration\Access;
use Gcego\Forms\Configuration\Condition;
use Gcego\Forms\Configuration\DisabledWhen;
use Gcego\Forms\Configuration\RequiredWhen;
use Gcego\Forms\Configuration\Rules;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Fields\Field;
use Gcego\Security\Contracts\Requirement;

/**
 * The fluent form of a form field:
 *
 *     TextInput::define('first_name', 'First Name')->required()->maxLength(100)
 *     EmailInput::define('email', 'Email')->rules(fn (?Teacher $record) => [Rule::unique('teachers')->ignore($record)])
 *     Textarea::define('address', 'Address')->columnSpan(2)->visibleWhen('has_address')
 *
 * Every field property is available by name (placeholder(), default(),
 * helperText(), live(), disabled(), and so on).
 *
 * visible(), hidden(), required() and disabled() also take Filament-style
 * closures; the fields they read become live automatically:
 *
 *     TextInput::make('company')->visible(fn (Get $get): bool => $get('type') === 'business')
 *     TextInput::make('vat')->required(fn (Get $get, ?Model $record): bool => $get('country') === 'CM')
 */
class FieldBuilder extends ComponentBuilder
{
    /**
     * Show (validate and save) the field only while the closure returns true.
     */
    public function visible(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure
            ? $this->configure(new VisibleWhen(Condition::computed($condition)))
            : $this->set(Field::Hidden, ! $condition);
    }

    /**
     * Hide the field (never rendered, validated or saved) while the closure returns true.
     */
    public function hidden(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure
            ? $this->configure(new VisibleWhen(Condition::computed($condition, negate: true)))
            : $this->set(Field::Hidden, $condition);
    }

    public function required(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure
            ? $this->configure(new RequiredWhen(Condition::computed($condition)))
            : $this->set(Field::Required, $condition);
    }

    /**
     * Disabled fields are rendered but their submitted values are ignored.
     */
    public function disabled(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure
            ? $this->configure(new DisabledWhen(Condition::computed($condition)))
            : $this->set(Field::Disabled, $condition);
    }

    /**
     * Run after the user changes the field (Livewire host): fn (Set $set, mixed $state, mixed $old).
     * Values it sets must be declared fields; they are validated on submit like any input.
     */
    public function afterStateUpdated(Closure $callback): static
    {
        return $this->set(Field::AfterStateUpdated, $callback);
    }

    /**
     * Add Laravel validation rules: strings, rule objects, or a closure fn (?Model $record): array.
     */
    public function rules(string|object ...$rules): static
    {
        return $this->configure(new Rules(...$rules));
    }

    /**
     * Who may read and write the field; others do not see it (or see a mask) and cannot submit it.
     */
    public function access(?Requirement $read = null, ?Requirement $write = null, bool $mask = false): static
    {
        return $this->replaceConfiguration(new Access($read, $write, $mask));
    }

    /**
     * Show (validate and save) the field only while another field matches.
     *
     * @param  scalar|\BackedEnum|list<scalar|\BackedEnum>|null  $value
     */
    public function visibleWhen(string|Condition $field, mixed $value = true): static
    {
        return $this->configure(new VisibleWhen($field, $value));
    }

    /**
     * @param  scalar|\BackedEnum|list<scalar|\BackedEnum>|null  $value
     */
    public function requiredWhen(string|Condition $field, mixed $value = true): static
    {
        return $this->configure(new RequiredWhen($field, $value));
    }

    /**
     * @param  scalar|\BackedEnum|list<scalar|\BackedEnum>|null  $value
     */
    public function disabledWhen(string|Condition $field, mixed $value = true): static
    {
        return $this->configure(new DisabledWhen($field, $value));
    }

    /**
     * Show the field only while the callback returns true: fn (FormState $state, ?string $item): bool.
     *
     * @param  list<string>  $dependsOn
     */
    public function visibleUsing(Closure $callback, array $dependsOn = []): static
    {
        return $this->configure(VisibleWhen::using($callback, $dependsOn));
    }
}
