<?php

declare(strict_types=1);

namespace Gcego\Forms;

use Gcego\Forms\Exceptions\InvalidFieldException;

/**
 * Validated gcego-forms configuration.
 */
final readonly class FormsConfig
{
    /**
     * @param  list<string>  $uploadDisks  Disks FileUpload fields may store to.
     */
    public function __construct(
        public string $statePath = 'data',
        public bool $trimStrings = true,
        public int $maxUploadKb = 10240,
        public array $uploadDisks = ['local'],
        public string $uploadDirectory = 'gcego-uploads',
        public int $optionsLimit = 100,
        public ?string $timezone = null,
        public string $liveDebounce = '400ms',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $statePath) !== 1) {
            throw InvalidFieldException::invalid('gcego-forms.state.path', 'it must be a lowercase identifier.');
        }

        if ($maxUploadKb < 1 || $optionsLimit < 1 || $optionsLimit > 100) {
            throw InvalidFieldException::invalid('gcego-forms configuration', 'uploads.max_size_kb must be positive and options.limit between 1 and 100.');
        }

        if ($uploadDisks === []) {
            throw InvalidFieldException::invalid('gcego-forms.uploads.disks', 'list at least one disk.');
        }

        if (preg_match('/^\d{1,4}ms$/', $liveDebounce) !== 1) {
            throw InvalidFieldException::invalid('gcego-forms.reactive.debounce', 'use milliseconds such as "400ms".');
        }
    }

    /**
     * @param  array<mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $string = static fn (string $path, ?string $default): ?string => is_string($value = data_get($config, $path, $default)) || $value === null ? $value : throw InvalidFieldException::invalid('gcego-forms.'.$path, 'it must be a string.');
        $int = static fn (string $path, int $default): int => is_int($value = data_get($config, $path, $default)) ? $value : throw InvalidFieldException::invalid('gcego-forms.'.$path, 'it must be an integer.');
        $disks = data_get($config, 'uploads.disks', ['local']);
        $trim = data_get($config, 'state.trim_strings', true);

        if (! is_array($disks) || array_filter($disks, static fn (mixed $disk): bool => ! is_string($disk)) !== []) {
            throw InvalidFieldException::invalid('gcego-forms.uploads.disks', 'it must be a list of disk names.');
        }

        return new self(
            statePath: $string('state.path', 'data') ?? 'data',
            trimStrings: is_bool($trim) ? $trim : throw InvalidFieldException::invalid('gcego-forms.state.trim_strings', 'it must be a boolean.'),
            maxUploadKb: $int('uploads.max_size_kb', 10240),
            uploadDisks: array_values(array_filter($disks, is_string(...))),
            uploadDirectory: $string('uploads.directory', 'gcego-uploads') ?? 'gcego-uploads',
            optionsLimit: $int('options.limit', 100),
            timezone: $string('dates.timezone', null),
            liveDebounce: $string('reactive.debounce', '400ms') ?? '400ms',
        );
    }
}
