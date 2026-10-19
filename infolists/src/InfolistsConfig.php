<?php

declare(strict_types=1);

namespace Gcego\Infolists;

use Gcego\Infolists\Exceptions\InvalidEntryException;

/**
 * Validated gcego-infolists configuration (config/gcego-infolists.php).
 */
final readonly class InfolistsConfig
{
    public function __construct(
        public string $placeholder = '—',
        public ?string $dateFormat = null,
        public ?string $dateTimeFormat = null,
        public ?string $timezone = null,
        public int $defaultColumns = 2,
        public int $maxListItems = 100,
        public int $temporaryUrlMinutes = 5,
    ) {
        $check = static function (bool $valid, string $key, string $reason): void {
            if (! $valid) {
                throw InvalidEntryException::invalid('gcego-infolists.'.$key, $reason);
            }
        };

        $check(mb_strlen($placeholder) <= 20, 'formatting.empty_placeholder', 'use at most 20 characters.');
        $check($timezone === null || in_array($timezone, timezone_identifiers_list(), true), 'formatting.timezone', 'use a valid timezone identifier or null.');
        $check($defaultColumns >= 1 && $defaultColumns <= 4, 'layouts.default_columns', 'use 1 to 4.');
        $check($maxListItems >= 1 && $maxListItems <= 1000, 'lists.max_items', 'use 1 to 1000.');
        $check($temporaryUrlMinutes >= 1 && $temporaryUrlMinutes <= 60, 'media.temporary_url_minutes', 'use 1 to 60.');
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $int = static function (string $key, int $default) use ($config): int {
            $value = data_get($config, $key, $default);

            return is_int($value) ? $value : throw InvalidEntryException::invalid('gcego-infolists.'.$key, 'it must be an integer.');
        };
        $string = static function (string $key, ?string $default) use ($config): ?string {
            $value = data_get($config, $key, $default);

            return $value === null || is_string($value) ? $value : throw InvalidEntryException::invalid('gcego-infolists.'.$key, 'it must be a string.');
        };

        return new self(
            placeholder: $string('formatting.empty_placeholder', '—') ?? '—',
            dateFormat: $string('formatting.date_format', null),
            dateTimeFormat: $string('formatting.datetime_format', null),
            timezone: $string('formatting.timezone', null),
            defaultColumns: $int('layouts.default_columns', 2),
            maxListItems: $int('lists.max_items', 100),
            temporaryUrlMinutes: $int('media.temporary_url_minutes', 5),
        );
    }
}
