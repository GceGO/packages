<?php

declare(strict_types=1);

namespace Gcego\Media\Transformations;

use Gcego\Media\Definitions\Fit;
use Gcego\Media\Definitions\Variant;
use Gcego\Media\Exceptions\MediaProcessingException;
use Gcego\Media\MediaConfig;
use GdImage;

/**
 * Image transformations with PHP's GD extension: orientation correction,
 * resize (contain, cover/crop, fill, width), format conversion (jpg, png,
 * webp) and quality. Inputs are checked against the pixel budget before
 * decoding; outputs never carry EXIF data. Only trusted Variant
 * definitions are applied (never request parameters).
 */
final readonly class ImageTransformer
{
    public const array Readable = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private MediaConfig $config) {}

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
    }

    /**
     * The encoded variant.
     *
     * @return array{contents: string, width: int, height: int, mime: string}
     *
     * @throws MediaProcessingException
     */
    public function variant(string $path, string $type, Variant $variant): array
    {
        $image = $this->load($path, $type);
        [$width, $height] = [imagesx($image), imagesy($image)];
        $box = [$variant->width(), $variant->height() ?? $variant->width()];
        $fit = $variant->fit();

        [$targetWidth, $targetHeight, $sourceX, $sourceY, $sourceWidth, $sourceHeight] = match ($fit) {
            Fit::Fill => [$box[0], $box[1], 0, 0, $width, $height],
            Fit::Cover => self::cover($width, $height, $box[0], $box[1]),
            Fit::Width => self::contain($width, $height, $box[0], PHP_INT_MAX),
            Fit::Contain => self::contain($width, $height, $box[0], $box[1]),
        };

        $output = imagecreatetruecolor(max(1, $targetWidth), max(1, $targetHeight));
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagefill($output, 0, 0, (int) imagecolorallocatealpha($output, 255, 255, 255, $variant->format() === 'jpg' ? 0 : 127));
        imagecopyresampled($output, $image, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return ['contents' => $this->encode($output, $variant->format(), $variant->quality()), 'width' => $targetWidth, 'height' => $targetHeight, 'mime' => self::mimeOf($variant->format())];
    }

    /**
     * The original re-encoded without metadata (orientation applied), for JPEG and WebP.
     *
     * @return array{contents: string, width: int, height: int}|null null when nothing needs stripping
     */
    public function stripped(string $path, string $type): ?array
    {
        if (! in_array($type, ['image/jpeg', 'image/webp'], true)) {
            return null;
        }

        $image = $this->load($path, $type);

        return ['contents' => $this->encode($image, $type === 'image/jpeg' ? 'jpg' : 'webp', 90), 'width' => imagesx($image), 'height' => imagesy($image)];
    }

    /**
     * EXIF orientation (1–8), or 1.
     */
    public static function orientation(string $path, string $type): int
    {
        if ($type !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * @throws MediaProcessingException
     */
    private function load(string $path, string $type): GdImage
    {
        if (! self::available()) {
            throw MediaProcessingException::because('The GD extension is not available.');
        }

        if (! in_array($type, self::Readable, true)) {
            throw MediaProcessingException::because('Not a raster image.');
        }

        $size = @getimagesize($path);

        if (! is_array($size) || $this->config->maxPixels < $size[0] * $size[1]) {
            throw MediaProcessingException::because('The image is unreadable or too large to process.');
        }

        $image = match ($type) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
        };

        if (! $image instanceof GdImage) {
            throw MediaProcessingException::because('The image could not be decoded.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $this->orient($image, self::orientation($path, $type));
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);

            if ($rotated instanceof GdImage) {
                return $rotated;
            }
        }

        return $image;
    }

    private function encode(GdImage $image, string $format, int $quality): string
    {
        ob_start();

        $ok = match ($format) {
            'jpg' => imagejpeg($image, null, $quality),
            'png' => imagepng($image, null, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, $quality) : imagepng($image, null, 6),
            default => false,
        };

        $contents = (string) ob_get_clean();

        if (! $ok || $contents === '') {
            throw MediaProcessingException::because('The image could not be encoded as '.$format.'.');
        }

        return $contents;
    }

    /**
     * @return array{int, int, int, int, int, int}
     */
    private static function contain(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $scale = min(1.0, $maxWidth / $width, $maxHeight / $height);

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), 0, 0, $width, $height];
    }

    /**
     * @return array{int, int, int, int, int, int}
     */
    private static function cover(int $width, int $height, int $boxWidth, int $boxHeight): array
    {
        $ratio = $boxWidth / $boxHeight;

        if ($width / $height > $ratio) {
            $cropWidth = (int) round($height * $ratio);

            return [$boxWidth, $boxHeight, (int) floor(($width - $cropWidth) / 2), 0, $cropWidth, $height];
        }

        $cropHeight = (int) round($width / $ratio);

        return [$boxWidth, $boxHeight, 0, (int) floor(($height - $cropHeight) / 2), $width, $cropHeight];
    }

    public static function mimeOf(string $format): string
    {
        return match ($format) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => function_exists('imagewebp') ? 'image/webp' : 'image/png',
        };
    }
}
