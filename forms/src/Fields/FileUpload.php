<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Forms\FormsConfig;

/**
 * File upload through Livewire's temporary uploads and Laravel storage:
 *
 *     new FileUpload('syllabus', 'Syllabus', new Property(FileUpload::Accept, ['pdf']), new Property(FileUpload::MaxSize, 4096))
 *
 * - Validated server-side: Laravel's file, max (KB) and mimes rules; mimes
 *   checks the content-detected type, not the browser's claim.
 * - Stored on submit with a random name, private visibility, on an allowed
 *   disk (gcego-forms.uploads.disks) under Directory.
 * - The stored value is the path. A submitted path is accepted only when it is
 *   the record's current value, so a path cannot be pointed at another file.
 * Uploads need the Livewire FormHost (WithFileUploads).
 */
class FileUpload extends Field
{
    public const string Multiple = 'multiple';

    public const string MaxFiles = 'max_files';

    /** Maximum size per file in KB; capped by gcego-forms.uploads.max_size_kb. */
    public const string MaxSize = 'max_size';

    /** Allowed extensions, checked against the detected content type: ['pdf', 'png']. */
    public const string Accept = 'accept';

    public const string Disk = 'disk';

    public const string Directory = 'directory';

    public function view(): string
    {
        return 'gcego-forms::fields.file-upload';
    }

    public function isMultiple(): bool
    {
        return $this->get(self::Multiple) === true;
    }

    public function maxSize(FormsConfig $config): int
    {
        return min($this->intProperty(self::MaxSize) ?? $config->maxUploadKb, $config->maxUploadKb);
    }

    /**
     * @return list<string>
     */
    public function accepted(): array
    {
        $accept = $this->get(self::Accept);

        return is_array($accept) ? array_values(array_filter($accept, is_string(...))) : [];
    }

    public function disk(FormsConfig $config): string
    {
        return $this->stringProperty(self::Disk) ?? $config->uploadDisks[0];
    }

    public function directory(FormsConfig $config): string
    {
        return $this->stringProperty(self::Directory) ?? $config->uploadDirectory;
    }

    /**
     * Rules for one new file.
     *
     * @return list<string>
     */
    public function fileRules(FormsConfig $config): array
    {
        $rules = ['file', 'max:'.$this->maxSize($config)];

        if ($this->accepted() !== []) {
            $rules[] = 'mimes:'.implode(',', $this->accepted());
        }

        return $rules;
    }

    public function normalizeInput(mixed $value, FormsConfig $config): mixed
    {
        if ($this->isMultiple()) {
            return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => $item !== null && $item !== '')) : ($value === null || $value === '' ? [] : [$value]);
        }

        return $value === '' ? null : $value;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Multiple, Type::bool(), default: false),
            new PropertyDefinition(self::MaxFiles, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 50, ruleDescription: 'between 1 and 50'),
            new PropertyDefinition(self::MaxSize, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1, ruleDescription: 'a positive size in KB'),
            new PropertyDefinition(self::Accept, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $ext): bool => ! is_string($ext) || preg_match('/^[a-z0-9]{1,10}$/', $ext) !== 1) === [], ruleDescription: 'a list of lowercase extensions'),
            new PropertyDefinition(self::Disk, Type::string()),
            new PropertyDefinition(self::Directory, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#i', $value) === 1, ruleDescription: 'a relative directory without ".."'),
        );
    }
}
