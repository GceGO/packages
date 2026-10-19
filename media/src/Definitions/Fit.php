<?php

declare(strict_types=1);

namespace Gcego\Media\Definitions;

/**
 * How an image fills a variant box.
 */
enum Fit: string
{
    /** Inside the box, keeping the aspect ratio (never upscaled). */
    case Contain = 'contain';

    /** Fill the box, cropping the overflow (centered). */
    case Cover = 'cover';

    /** Exactly the box, distorting if needed. */
    case Fill = 'fill';

    /** Width only (height follows), never upscaled. */
    case Width = 'width';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $fit): string => $fit->value, self::cases());
    }
}
