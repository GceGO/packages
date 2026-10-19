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
 */
class FieldBuilder extends ComponentBuilder
{
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
