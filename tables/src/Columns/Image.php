<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A small, lazily loaded image:
 *
 *     new Image('avatar_path', 'Photo', new Property(Image::Disk, 'public'), new Property(Image::AltColumn, 'name'), new Property(Image::Round, true))
 *     new Image('logo_url', 'Logo', new Property(Image::Fallback, '/images/placeholder.png'))
 *
 * Values are either http(s) URLs or storage paths on the configured Disk
 * (paths with "..", schemes or control characters are refused). Anything
 * else shows the fallback (or an icon). Alt text comes from AltColumn or Alt.
 */
class Image extends Column
{
    /** A filesystem disk: values are paths on it (its url() is used). */
    public const string Disk = 'disk';

    public const string Alt = 'alt';

    /** A column or relation path whose value is the alt text. */
    public const string AltColumn = 'alt_column';

    /** A trusted URL used when the value is empty or invalid. */
    public const string Fallback = 'fallback';

    public const string Round = 'round';

    public function cellView(): string
    {
        return 'gcego-tables::columns.image';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $altColumn = $this->stringProperty(self::AltColumn);
        $alt = $altColumn === null ? $this->stringProperty(self::Alt) : Column::stringify(ValueResolver::get($record, $altColumn));

        return [
            'src' => $this->url($value) ?? $this->stringProperty(self::Fallback),
            'alt' => $alt === null ? '' : (string) __($alt),
            'round' => $this->get(self::Round) === true,
            'text' => null,
        ];
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return $this->url($value);
    }

    public function url(mixed $value): ?string
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
            return SafeUrl::web(Storage::disk($disk)->url($value));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        $alt = $this->stringProperty(self::AltColumn);

        return array_values(array_filter([$this->relation(), $alt !== null && str_contains($alt, '.') ? substr($alt, 0, (int) strrpos($alt, '.')) : null]));
    }

    protected function defaultAlign(): string
    {
        return 'center';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Disk, Type::string()),
            new PropertyDefinition(self::Alt, Type::string()),
            new PropertyDefinition(self::AltColumn, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Column::NamePattern, $value) === 1, ruleDescription: 'a column name or relation path'),
            new PropertyDefinition(self::Fallback, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && SafeUrl::web($value) !== null, ruleDescription: 'an http(s) or relative URL'),
            new PropertyDefinition(self::Round, Type::bool(), default: false),
        );
    }
}
