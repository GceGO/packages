<?php

declare(strict_types=1);

namespace Gcego\Media;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Definitions\Variant;

/**
 * What media is allowed and how it is kept: disk, visibility, accepted
 * content types, size and count limits, single-file behavior and image
 * variants. MediaCollection (a named set) builds on it.
 *
 * Immutable: the fluent methods return changed copies.
 *
 *     (new MediaCollection('lesson-resources'))->disk('local')->private()->maxFiles(20)
 *
 * Construction never touches storage or the database.
 */
abstract class Media extends Component
{
    public const string Disk = 'disk';

    /** private or public. */
    public const string Visibility = 'visibility';

    /** Accepted content types ("image/png", "image/*"), checked against the file's detected type. */
    public const string Types = 'types';

    /** Kilobytes per file (never above gcego-media.uploads.max_size). */
    public const string MaxSize = 'max_size';

    public const string MaxFiles = 'max_files';

    /** One file at a time: attaching a new one replaces the previous one. */
    public const string Single = 'single';

    /** Image dimension limits in pixels. */
    public const string MinWidth = 'min_width';

    public const string MinHeight = 'min_height';

    public const string MaxWidth = 'max_width';

    public const string MaxHeight = 'max_height';

    public const array ImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public const string TypePattern = '/^[a-z]+\/(?:\*|[a-z0-9][a-z0-9.+-]{0,126})$/';

    public function disk(string $disk): static
    {
        return $this->withProperty(new Property(self::Disk, $disk));
    }

    public function private(): static
    {
        return $this->withProperty(new Property(self::Visibility, MediaVisibility::Private->value));
    }

    public function public(): static
    {
        return $this->withProperty(new Property(self::Visibility, MediaVisibility::Public->value));
    }

    /**
     * @param  list<string>  $types
     */
    public function acceptedFileTypes(array $types): static
    {
        return $this->withProperty(new Property(self::Types, $types));
    }

    public function image(): static
    {
        return $this->acceptedFileTypes(self::ImageTypes);
    }

    public function maxSize(int $kilobytes): static
    {
        return $this->withProperty(new Property(self::MaxSize, $kilobytes));
    }

    public function maxFiles(int $files): static
    {
        return $this->withProperty(new Property(self::MaxFiles, $files));
    }

    public function single(): static
    {
        return $this->withProperty(new Property(self::Single, true));
    }

    public function variants(Variant ...$variants): static
    {
        return $this->withAppendedChildren(...$variants);
    }

    public function dimensions(?int $minWidth = null, ?int $minHeight = null, ?int $maxWidth = null, ?int $maxHeight = null): static
    {
        $properties = [];

        foreach ([self::MinWidth => $minWidth, self::MinHeight => $minHeight, self::MaxWidth => $maxWidth, self::MaxHeight => $maxHeight] as $key => $value) {
            if ($value !== null) {
                $properties[] = new Property($key, $value);
            }
        }

        return $this->withProperty(...$properties);
    }

    public function diskName(): ?string
    {
        return $this->text(self::Disk);
    }

    public function visibility(): ?MediaVisibility
    {
        return MediaVisibility::tryFrom((string) $this->text(self::Visibility));
    }

    /**
     * @return list<string>|null
     */
    public function types(): ?array
    {
        $types = $this->get(self::Types);

        return is_array($types) ? array_values(array_filter($types, is_string(...))) : null;
    }

    public function sizeLimit(): ?int
    {
        return $this->integer(self::MaxSize);
    }

    public function fileLimit(): ?int
    {
        return $this->integer(self::MaxFiles);
    }

    public function isSingle(): bool
    {
        return $this->get(self::Single) === true;
    }

    /**
     * @return array{min_width: int|null, min_height: int|null, max_width: int|null, max_height: int|null}
     */
    public function dimensionLimits(): array
    {
        return ['min_width' => $this->integer(self::MinWidth), 'min_height' => $this->integer(self::MinHeight), 'max_width' => $this->integer(self::MaxWidth), 'max_height' => $this->integer(self::MaxHeight)];
    }

    /**
     * @return list<Variant>
     */
    public function variantDefinitions(): array
    {
        $variants = [];

        foreach ($this->children() as $child) {
            if ($child instanceof Variant) {
                $variants[] = $child;
            }
        }

        return $variants;
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $names = array_map(static fn (Variant $variant): string => $variant->variantName(), $this->variantDefinitions());

        return count($names) === count(array_unique($names)) ? [] : ['each variant needs its own name.'];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $positive = static fn (int $max): \Closure => static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= $max;

        return parent::defineSchema()
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Disk, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $value) === 1, ruleDescription: 'a disk name'),
                new PropertyDefinition(self::Visibility, Type::string(), allowedValues: ['private', 'public']),
                new PropertyDefinition(self::Types, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $type): bool => ! is_string($type) || preg_match(self::TypePattern, $type) !== 1) === [], ruleDescription: 'a list of content types such as "image/png" or "image/*"'),
                new PropertyDefinition(self::MaxSize, Type::int(), rule: $positive(10_485_760), ruleDescription: 'a positive size in KB'),
                new PropertyDefinition(self::MaxFiles, Type::int(), rule: $positive(500), ruleDescription: 'between 1 and 500'),
                new PropertyDefinition(self::Single, Type::bool(), default: false),
                new PropertyDefinition(self::MinWidth, Type::int(), rule: $positive(100_000), ruleDescription: 'pixels'),
                new PropertyDefinition(self::MinHeight, Type::int(), rule: $positive(100_000), ruleDescription: 'pixels'),
                new PropertyDefinition(self::MaxWidth, Type::int(), rule: $positive(100_000), ruleDescription: 'pixels'),
                new PropertyDefinition(self::MaxHeight, Type::int(), rule: $positive(100_000), ruleDescription: 'pixels'),
            )
            ->withAllowedChildren([Variant::class])
            ->withConstraint(static fn (ComponentInterface $media): array => $media instanceof self ? $media->problems() : []);
    }

    private function text(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(string $key): ?int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : null;
    }
}
