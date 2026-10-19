<?php

declare(strict_types=1);

namespace Gcego\Settings\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NoProfanity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && str_contains(strtolower($value), 'darn')) {
            $fail('The :attribute must be polite.');
        }
    }
}
