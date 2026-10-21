<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\FormsConfig;
use Illuminate\Validation\Rules\Password;

/**
 * A masked password field:
 *
 *     new PasswordInput('password', 'New password', new Property(PasswordInput::Confirmed, true), new Property(PasswordInput::Strong, true))
 *
 * Never hydrated (no value, hash or placeholder reaches the browser), an empty
 * submission leaves the stored password unchanged, and values are never put
 * in messages. Hashing is the application's job (a model cast or mutator).
 */
class PasswordInput extends TextInput
{
    /** Require a matching "<name>_confirmation" input. */
    public const string Confirmed = 'confirmed';

    /** Offer a show/hide control. */
    public const string Revealable = 'revealable';

    /** Apply Laravel's Password::defaults() rule. */
    public const string Strong = 'strong';

    public function view(): string
    {
        return 'gcego-forms::fields.password';
    }

    public function isConfirmed(): bool
    {
        return $this->get(self::Confirmed) === true;
    }

    public function typeRules(): array
    {
        $rules = array_values(array_filter(parent::typeRules(), static fn (mixed $rule): bool => $rule !== 'string'));
        $rules = ['string', ...$rules];

        if ($this->isConfirmed()) {
            $rules[] = 'confirmed';
        }

        if ($this->get(self::Strong) === true) {
            $rules[] = Password::defaults();
        }

        return $rules;
    }

    public function skipsEmpty(): bool
    {
        return true;
    }

    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        return null;
    }

    public function emptyState(): mixed
    {
        return null;
    }

    public function normalizeInput(mixed $value, FormsConfig $config): mixed
    {
        // Passwords are never trimmed: spaces are part of them.
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Confirmed, Type::bool(), default: false),
            new PropertyDefinition(self::Revealable, Type::bool(), default: true),
            new PropertyDefinition(self::Strong, Type::bool(), default: false),
        );
    }

    protected static function defaultType(): string
    {
        return 'password';
    }

    protected static function defaultAutocomplete(): string
    {
        return 'new-password';
    }
}
