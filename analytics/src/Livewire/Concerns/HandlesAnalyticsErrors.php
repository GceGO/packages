<?php

declare(strict_types=1);

namespace Gcego\Analytics\Livewire\Concerns;

use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsException;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;

/**
 * Accessible messages for refused or failed analytics, never internals.
 */
trait HandlesAnalyticsErrors
{
    protected function messageFor(AnalyticsException $exception): string
    {
        return match (true) {
            $exception instanceof AnalyticsQueryException => $exception->getMessage(),
            $exception instanceof AnalyticsAuthorizationException => (string) __('gcego-analytics::messages.errors.forbidden'),
            $exception instanceof AnalyticsNotFoundException => (string) __('gcego-analytics::messages.errors.missing'),
            default => (string) __('gcego-analytics::messages.errors.failed'),
        };
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function cleanFilters(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $key) === 1 && (is_scalar($value) || is_array($value))) {
                $clean[$key] = is_array($value) ? array_values(array_filter(array_slice($value, 0, 100), is_scalar(...))) : $value;
            }
        }

        return $clean;
    }
}
