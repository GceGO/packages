<?php

declare(strict_types=1);

namespace Gcego\Media\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\MediaManager;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Bytes;
use Gcego\Media\Support\Keys;
use Illuminate\Database\Eloquent\Model;

/**
 * A gcego/infolists preview of a collection's media (read-only):
 *
 *     new MediaPreview('lesson_video', 'Lesson Video')
 *     new MediaPreview('attachments', 'Attachments', new Property(MediaPreview::Limit, 20))
 *
 * Images show their preview (alt text from the media), PDFs, audio and
 * video their native player with the file's metadata (duration,
 * dimensions), other files an icon, name, type and size, each with a
 * download link. Only media the viewer may see is listed; URLs are the
 * authorized media routes. Uploaded content is never embedded as HTML.
 */
class MediaPreview extends Entry
{
    /** At most this many files (default 12). */
    public const string Limit = 'limit';

    /** Show PDFs, audio and video in place (default true); false lists them as files. */
    public const string Players = 'players';

    public function view(): string
    {
        return 'gcego-media::entries.preview';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $files = Keys::mediaOf($record, $this->entryName());
        $access = app(MediaAccess::class);
        $manager = app(MediaManager::class);
        $limit = is_int($limit = $this->get(self::Limit)) ? $limit : 12;
        $items = [];

        foreach ($files as $media) {
            if (! $media instanceof MediaRecord || ! $access->allows(MediaAbility::View, $media, $record instanceof Model ? $record : null) || count($items) >= $limit) {
                continue;
            }

            $items[] = [
                'kind' => $media->kind(),
                'name' => $media->original_name,
                'alt' => $media->alt ?? '',
                'mime' => $media->mime,
                'size' => Bytes::format($media->size),
                'width' => is_int($width = $media->meta('width')) ? $width : null,
                'height' => is_int($height = $media->meta('height')) ? $height : null,
                'duration' => is_numeric($duration = $media->meta('duration')) ? gmdate((float) $duration >= 3600 ? 'H:i:s' : 'i:s', (int) round((float) $duration)) : null,
                'src' => $manager->url($media, $media->kind() === 'image' && $media->variant('preview') !== null ? 'preview' : null),
                'download' => $manager->url($media, download: true),
            ];
        }

        return ['items' => $items, 'players' => $this->get(self::Players) !== false, 'text' => $items === [] ? null : (string) count($items)];
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['gcegoMedia'];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Limit, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 100, ruleDescription: 'between 1 and 100'),
            new PropertyDefinition(self::Players, Type::bool(), default: true),
        );
    }
}
