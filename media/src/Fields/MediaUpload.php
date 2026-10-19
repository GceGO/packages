<?php

declare(strict_types=1);

namespace Gcego\Media\Fields;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Forms\Contracts\PersistsWithRecord;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\FormsConfig;
use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Attachments\MediaAttachments;
use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\Media;
use Gcego\Media\MediaCollection;
use Gcego\Media\MediaConfig;
use Gcego\Media\Support\Keys;
use Gcego\Media\Uploads\UploadContext;
use Gcego\Media\Uploads\UploadManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * A gcego/forms upload field backed by gcego/media:
 *
 *     new MediaUpload('image', 'Image')
 *     (new MediaUpload('cover_image', 'Cover Image'))->image()->maxFileSize(5120)
 *     (new MediaUpload('attachments', 'Attachments'))->multiple()->maxFiles(10)
 *     (new MediaUpload('documents', 'Documents'))->private()->acceptedFileTypes(['application/pdf'])
 *
 * It uses the form's Livewire uploads (progress, temporary files) and its
 * validation, then: each file's content is verified (UploadValidator) and
 * the temporary file must belong to the user; after the record is saved
 * (inside the gcego/actions transaction) the files are stored and
 * attached to it in the collection (default: the field name), and media
 * the user removed is detached. The form value is a list of media ULIDs;
 * only media already attached to the record may be kept.
 *
 * Not a column: the model needs the HasMedia trait, not an attribute.
 */
class MediaUpload extends FileUpload implements PersistsWithRecord
{
    /** The media collection (default: the field name with "_" and "-" kept). */
    public const string Collection = 'collection';

    /** Accepted content types ("image/png", "image/*"), checked against the content. */
    public const string Types = 'types';

    /** private or public. */
    public const string Visibility = 'visibility';

    public function view(): string
    {
        return 'gcego-media::uploads.field';
    }

    public function image(): static
    {
        return $this->acceptedFileTypes(Media::ImageTypes);
    }

    /**
     * @param  list<string>  $types
     */
    public function acceptedFileTypes(array $types): static
    {
        return $this->withProperty(new Property(self::Types, $types));
    }

    public function multiple(bool $multiple = true): static
    {
        return $this->withProperty(new Property(self::Multiple, $multiple));
    }

    public function maxFiles(int $files): static
    {
        return $this->withProperty(new Property(self::MaxFiles, $files), new Property(self::Multiple, true));
    }

    /**
     * Kilobytes per file. (FileUpload::maxSize() is gcego/forms' reader of the effective limit.)
     */
    public function maxFileSize(int $kilobytes): static
    {
        return $this->withProperty(new Property(self::MaxSize, $kilobytes));
    }

    /**
     * Store on this disk (one of gcego-media.disks). (FileUpload::disk() is gcego/forms' reader.)
     */
    public function onDisk(string $disk): static
    {
        return $this->withProperty(new Property(self::Disk, $disk));
    }

    public function collection(string $collection): static
    {
        return $this->withProperty(new Property(self::Collection, $collection));
    }

    public function private(): static
    {
        return $this->withProperty(new Property(self::Visibility, 'private'));
    }

    public function public(): static
    {
        return $this->withProperty(new Property(self::Visibility, 'public'));
    }

    public function collectionName(): string
    {
        $collection = $this->get(self::Collection);

        return is_string($collection) ? $collection : $this->fieldName();
    }

    /**
     * The field's limits as a media definition (they tighten the collection's).
     */
    public function restrictions(): MediaCollection
    {
        $properties = [new Property(MediaCollection::Name, $this->collectionName())];

        foreach ([self::Disk => Media::Disk, self::Visibility => Media::Visibility, self::Types => Media::Types, self::MaxSize => Media::MaxSize, self::MaxFiles => Media::MaxFiles] as $from => $to) {
            if ($this->get($from) !== null) {
                $properties[] = new Property($to, $this->get($from));
            }
        }

        if (! $this->isMultiple()) {
            $properties[] = new Property(Media::Single, true);
        }

        return new MediaCollection(...$properties);
    }

    public function rules(): CollectionRules
    {
        return CollectionRules::resolve(app(MediaConfig::class), app(MediaCollectionRegistry::class)->get($this->collectionName()), $this->restrictions());
    }

    public function maxSize(FormsConfig $config): int
    {
        return $this->rules()->maxSize;
    }

    /**
     * Laravel's file and size rules; the content checks run in stage().
     *
     * @return list<string>
     */
    public function fileRules(FormsConfig $config): array
    {
        return ['file', 'max:'.$this->rules()->maxSize];
    }

    /**
     * The ULIDs of the media attached to the record in this field's collection.
     */
    public function currentValue(Model $record): mixed
    {
        $ulids = $record->exists ? app(MediaAttachments::class)->media($record, $this->collectionName())->pluck('ulid')->all() : [];

        return $this->isMultiple() ? $ulids : ($ulids[0] ?? null);
    }

    /**
     * @return array{keep: list<string>, files: list<UploadedFile>}
     *
     * @throws ValidationException
     */
    public function stage(mixed $value, ?Model $record, FormsConfig $config): mixed
    {
        $items = is_array($value) ? $value : ($value === null ? [] : [$value]);
        $rules = $this->rules();
        $keep = [];
        $files = [];
        $errors = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $keep[] = strtolower($item);

                continue;
            }

            if (! $item instanceof UploadedFile) {
                continue;
            }

            try {
                app(UploadManager::class)->check($item, $rules);
                $files[] = $item;
            } catch (UploadException $exception) {
                $errors = [...$errors, ...array_map(static fn (string $error): string => FileName::prefix($item, $error), $exception->errors)];
            }
        }

        if (count($keep) + count($files) > ($rules->single ? 1 : $rules->maxFiles)) {
            $errors[] = (string) __('gcego-media::messages.errors.too_many', ['max' => $rules->maxFiles]);
        }

        if ($files !== [] && ! app(MediaAccess::class)->allows(MediaAbility::Upload)) {
            $errors[] = (string) __('gcego-media::messages.errors.denied');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([$this->fieldName() => $errors]);
        }

        return ['keep' => $keep, 'files' => $files];
    }

    /**
     * Store new files, attach them, and detach what the user removed.
     */
    public function persist(Model $record, mixed $staged): void
    {
        $kept = is_array($staged) && is_array($staged['keep'] ?? null) ? $staged['keep'] : [];
        $files = is_array($staged) && is_array($staged['files'] ?? null) ? array_values(array_filter($staged['files'], static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
        $collection = $this->collectionName();
        $attachments = app(MediaAttachments::class);
        $keep = [];

        foreach ($attachments->media($record, $collection) as $media) {
            if (in_array($media->ulid, $kept, true)) {
                $keep[] = Keys::int($media->getKey());
            }
        }

        $context = new UploadContext($collection, $record, $this->restrictions());
        $rules = app(UploadManager::class)->rules($context);
        $attachments->syncTrusted($record, $collection, $keep);

        foreach ($files as $file) {
            $keep[] = Keys::int(app(UploadManager::class)->store($file, $context, $rules)->getKey());
        }

        $attachments->syncTrusted($record, $collection, $keep);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('media.upload')
            ->withProperties(
                new PropertyDefinition(self::Collection, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(MediaCollection::NamePattern, $value) === 1, ruleDescription: 'a collection name'),
                new PropertyDefinition(self::Types, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $type): bool => ! is_string($type) || preg_match(Media::TypePattern, $type) !== 1) === [], ruleDescription: 'a list of content types such as "image/png"'),
                new PropertyDefinition(self::Visibility, Type::string(), allowedValues: ['private', 'public']),
            )
            ->withConstraint(static fn (ComponentInterface $field): array => $field instanceof self && preg_match(MediaCollection::NamePattern, $field->collectionName()) !== 1 ? ['the collection name "'.$field->collectionName().'" is invalid; set MediaUpload::Collection.'] : []);
    }
}
