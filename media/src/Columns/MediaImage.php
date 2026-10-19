<?php

declare(strict_types=1);

namespace Gcego\Media\Columns;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\MediaManager;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Keys;
use Gcego\Tables\Columns\Image;
use Gcego\Tables\TablesConfig;

/**
 * A gcego/tables thumbnail of the first media in a collection:
 *
 *     new MediaImage('cover_image', 'Cover')               // collection = column name
 *
 * The model needs gcego/media's HasMedia trait: the table eager-loads its
 * media (no query per row). The image URL is the authorized media route
 * (the "thumb" variant when there is one), so the browser can only load it
 * when the viewer may see the file.
 */
class MediaImage extends Image
{
    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $media = self::firstMedia($record, $this->columnName());
        $src = null;

        if ($media !== null && $media->isImage() && app(MediaAccess::class)->available($media)) {
            $src = app(MediaManager::class)->url($media, $media->variant('thumb') !== null ? 'thumb' : null);
        }

        $alt = $this->stringProperty(self::Alt);

        $fallbackAlt = $alt === null ? '' : (string) __($alt);

        return ['src' => $src ?? $this->stringProperty(self::Fallback), 'alt' => $media !== null && $media->alt !== null ? $media->alt : $fallbackAlt, 'round' => $this->get(self::Round) === true, 'text' => null];
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return self::firstMedia($record, $this->columnName())?->original_name;
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['gcegoMedia'];
    }

    public function isRelationPath(): bool
    {
        return false;
    }

    public function isSearchable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public static function firstMedia(mixed $record, string $collection): ?MediaRecord
    {
        return Keys::mediaOf($record, $collection)->first();
    }
}
