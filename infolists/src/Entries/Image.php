<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Tables\Support\ValueResolver;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * An image or media preview:
 *
 *     new Image('logo_url', 'Logo')                                                                   // an http(s) URL
 *     new Image('photo_path', 'Photo', new Property(Image::Disk, 'public'), new Property(Image::Round, true))
 *     new Image('scan_path', 'Scan', new Property(Image::Disk, 's3'), new Property(Image::Private, true))  // temporary URL
 *
 * Values are http(s) URLs, or storage paths on the configured Disk (paths
 * with "..", schemes or control characters are refused). Private media is
 * only shown through a short-lived temporary URL (media.temporary_url_minutes);
 * when the disk cannot sign URLs, nothing is shown: the storage path is
 * never exposed. Images are lazy-loaded with alt text (Alt, AltColumn, or the label).
 */
class Image extends Entry
{
    public const string Disk = 'disk';

    /** The file is private: use a temporary URL, never the plain one. */
    public const string Private = 'private';

    public const string Alt = 'alt';

    public const string AltColumn = 'alt_column';

    /** A trusted URL used when the value is empty or invalid. */
    public const string Fallback = 'fallback';

    public const string Round = 'round';

    /** sm | md | lg | xl */
    public const string Size = 'size';

    public const array Sizes = ['sm' => 'size-10', 'md' => 'size-16', 'lg' => 'size-24', 'xl' => 'size-40'];

    public function view(): string
    {
        return 'gcego-infolists::entries.image';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $altColumn = $this->stringProperty(self::AltColumn);
        $alt = $altColumn === null ? $this->stringProperty(self::Alt) : Formats::text(ValueResolver::get($record, $altColumn));
        $src = $this->url($value, $config) ?? $this->stringProperty(self::Fallback);

        return [
            'src' => $src,
            'alt' => $alt === null ? Formats::translate($this->label()) : Formats::translate($alt),
            'round' => $this->get(self::Round) === true,
            'size' => self::Sizes[$this->stringProperty(self::Size) ?? 'md'] ?? 'size-16',
            'text' => $src,
            'title' => null,
            'copy' => false,
        ];
    }

    public function url(mixed $value, InfolistsConfig $config): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $disk = $this->stringProperty(self::Disk);

        if ($disk === null) {
            return SafeUrl::web($value);
        }

        if (! SafeUrl::isStoragePath($value)) {
            return null;
        }

        try {
            $url = $this->get(self::Private) === true
                ? Storage::disk($disk)->temporaryUrl($value, Date::now()->addMinutes($config->temporaryUrlMinutes))
                : Storage::disk($disk)->url($value);
        } catch (Throwable) {
            return null;
        }

        return SafeUrl::web($url);
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        $alt = $this->stringProperty(self::AltColumn);

        return array_values(array_unique(array_filter([...parent::relations(), $alt !== null && str_contains($alt, '.') ? substr($alt, 0, (int) strrpos($alt, '.')) : null])));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Disk, Type::string()),
            new PropertyDefinition(self::Private, Type::bool(), default: false),
            new PropertyDefinition(self::Alt, Type::string()),
            new PropertyDefinition(self::AltColumn, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Entry::NamePattern, $value) === 1, ruleDescription: 'an attribute name or relation path'),
            new PropertyDefinition(self::Fallback, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && SafeUrl::web($value) !== null, ruleDescription: 'an http(s) or root-relative URL'),
            new PropertyDefinition(self::Round, Type::bool(), default: false),
            new PropertyDefinition(self::Size, Type::string(), default: 'md', allowedValues: array_keys(self::Sizes)),
        );
    }
}
