<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Forms\FormsConfig;

/**
 * A boolean checkbox. Required means "must be checked" (Laravel's accepted rule).
 * Only a submitted value changes the stored one: a missing key never writes false.
 */
class Checkbox extends Field
{
    public function view(): string
    {
        return 'gcego-forms::fields.checkbox';
    }

    public function typeRules(): array
    {
        return $this->isRequired() ? ['accepted'] : ['boolean'];
    }

    public function normalizeInput(mixed $value, FormsConfig $config): mixed
    {
        return match (true) {
            $value === true, $value === 1, $value === '1', $value === 'true', $value === 'on' => true,
            $value === false, $value === 0, $value === '0', $value === 'false', $value === null, $value === '' => false,
            default => $value,
        };
    }

    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        return (bool) $value;
    }

    public function emptyState(): mixed
    {
        return (bool) $this->get(self::Default);
    }
}
