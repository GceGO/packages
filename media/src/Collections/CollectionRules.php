<?php

declare(strict_types=1);

namespace Gcego\Media\Collections;

use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Definitions\Variant;
use Gcego\Media\Exceptions\InvalidMediaException;
use Gcego\Media\Media;
use Gcego\Media\MediaCollection;
use Gcego\Media\MediaConfig;

/**
 * The effective rules of an upload: configuration, then the collection,
 * then an optional narrower definition (a MediaUpload field). Limits only
 * ever tighten: sizes and counts take the smallest, types the intersection.
 */
final readonly class CollectionRules
{
    /**
     * @param  list<string>  $types
     * @param  list<Variant>  $variants
     * @param  array{min_width: int|null, min_height: int|null, max_width: int|null, max_height: int|null}  $dimensions
     */
    public function __construct(
        public string $collection,
        public string $disk,
        public MediaVisibility $visibility,
        public array $types,
        public int $maxSize,
        public int $maxFiles,
        public bool $single,
        public array $variants,
        public array $dimensions,
    ) {}

    public static function resolve(MediaConfig $config, MediaCollection $collection, ?Media $narrower = null): self
    {
        $disk = $narrower?->diskName() ?? $collection->diskName() ?? $config->disk;

        if (! in_array($disk, $config->disks, true)) {
            throw InvalidMediaException::invalid('Disk "'.$disk.'"', 'it is not one of gcego-media.disks.');
        }

        $types = $config->allowedTypes;

        foreach ([$collection->types(), $narrower?->types()] as $restriction) {
            if ($restriction !== null) {
                $types = $types === [] ? $restriction : self::intersect($types, $restriction);
            }
        }

        $limits = static fn (int $base, ?int ...$values): int => min([$base, ...array_values(array_filter($values, static fn (?int $value): bool => $value !== null))]);
        $dimensions = $collection->dimensionLimits();

        foreach ($narrower?->dimensionLimits() ?? [] as $key => $value) {
            if ($value !== null) {
                $dimensions[$key] = $value;
            }
        }

        $variants = [...$collection->variantDefinitions(), ...($narrower?->variantDefinitions() ?? [])];

        // Images of collections that declare no variants get gcego-media.images.default_variants.
        if ($variants === []) {
            foreach ($config->defaultVariants as $name => $settings) {
                $variants[] = Variant::fromArray($name, $settings);
            }
        }

        return new self(
            collection: $collection->collectionName(),
            disk: $disk,
            visibility: $narrower?->visibility() ?? $collection->visibility() ?? $config->visibility,
            types: $types,
            maxSize: $limits($config->maxSize, $collection->sizeLimit(), $narrower?->sizeLimit()),
            maxFiles: $narrower?->isSingle() === true || $collection->isSingle() ? 1 : $limits($config->maxFiles, $collection->fileLimit(), $narrower?->fileLimit()),
            single: $collection->isSingle() || $narrower?->isSingle() === true,
            variants: $variants,
            dimensions: $dimensions,
        );
    }

    public function accepts(string $type): bool
    {
        foreach ($this->types as $allowed) {
            if ($allowed === $type || (str_ends_with($allowed, '/*') && str_starts_with($type, substr($allowed, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    public function variant(string $name): ?Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->variantName() === $name) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Types allowed by both lists ("image/*" matches "image/png").
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private static function intersect(array $a, array $b): array
    {
        $matches = static function (string $pattern, string $type): bool {
            return $pattern === $type || (str_ends_with($pattern, '/*') && str_starts_with($type, substr($pattern, 0, -1)));
        };
        $result = [];

        foreach ($b as $type) {
            foreach ($a as $allowed) {
                if ($matches($allowed, $type)) {
                    $result[] = $type;
                } elseif ($matches($type, $allowed)) {
                    $result[] = $allowed;
                }
            }
        }

        return array_values(array_unique($result));
    }
}
