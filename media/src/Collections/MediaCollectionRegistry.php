<?php

declare(strict_types=1);

namespace Gcego\Media\Collections;

use Gcego\Components\Properties\Property;
use Gcego\Media\Definitions\Variant;
use Gcego\Media\Exceptions\InvalidMediaException;
use Gcego\Media\Media;
use Gcego\Media\MediaCollection;
use Gcego\Media\MediaConfig;

/**
 * Registered collections (trusted: configuration and boot-time code). A
 * name that is not registered gets the default rules under that name, so
 * a MediaUpload field works without registering anything.
 */
final class MediaCollectionRegistry
{
    /** @var array<string, MediaCollection> */
    private array $collections = [];

    public function __construct(private readonly MediaConfig $config)
    {
        foreach ($config->collections as $name => $settings) {
            $this->register(self::fromArray($name, $settings));
        }
    }

    public function register(MediaCollection $collection): void
    {
        $collection->assertValid();
        $this->collections[$collection->collectionName()] = $collection;
    }

    public function has(string $name): bool
    {
        return isset($this->collections[$name]);
    }

    public function get(string $name): MediaCollection
    {
        if (preg_match(MediaCollection::NamePattern, $name) !== 1 || strlen($name) > 64) {
            throw InvalidMediaException::invalid('Collection "'.mb_substr($name, 0, 64).'"', 'use a lowercase name such as "lesson-resources".');
        }

        return $this->collections[$name] ?? new MediaCollection(new Property(MediaCollection::Name, $name));
    }

    /**
     * @return array<string, MediaCollection>
     */
    public function all(): array
    {
        return $this->collections;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(string $name, array $settings): MediaCollection
    {
        $map = ['disk' => Media::Disk, 'visibility' => Media::Visibility, 'types' => Media::Types, 'max_size' => Media::MaxSize, 'max_files' => Media::MaxFiles, 'single' => Media::Single, 'min_width' => Media::MinWidth, 'min_height' => Media::MinHeight, 'max_width' => Media::MaxWidth, 'max_height' => Media::MaxHeight];
        $properties = [new Property(MediaCollection::Name, $name)];

        foreach ($map as $key => $property) {
            if (array_key_exists($key, $settings)) {
                $properties[] = new Property($property, $settings[$key]);
            }
        }

        $variants = [];

        foreach (is_array($settings['variants'] ?? null) ? $settings['variants'] : [] as $variant => $options) {
            if (! is_string($variant) || ! is_array($options)) {
                throw InvalidMediaException::invalid('gcego-media.collections.definitions.'.$name.'.variants', 'map variant names to settings.');
            }

            $variants[] = Variant::fromArray($variant, $options);
        }

        return (new MediaCollection(...$properties, ...$variants))->assertValid();
    }
}
