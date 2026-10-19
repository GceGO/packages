<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaConfig;

/**
 * Validates one file against the effective rules, from its content:
 *
 * - present, readable and within the size limit;
 * - its detected type allowed (the browser's type is ignored);
 * - its name's extension consistent with that type;
 * - not executable or active content (scripts, HTML, SVG unless allowed);
 * - raster images decodable, within the pixel budget and dimension limits.
 *
 * Returns what it found (InspectedFile), or throws an UploadException
 * with a user-facing reason.
 */
final readonly class UploadValidator
{
    public function __construct(private FileInspector $inspector, private MediaConfig $config) {}

    /**
     * @throws UploadException
     */
    public function validate(string $path, string $claimedName, CollectionRules $rules): InspectedFile
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw UploadException::refused('missing');
        }

        $size = filesize($path);

        if ($size === false || $size === 0) {
            throw UploadException::refused('empty');
        }

        if ($size > $rules->maxSize * 1024) {
            throw UploadException::refused('too_large', ['max' => $rules->maxSize]);
        }

        $type = $this->inspector->detectType($path);
        $name = FileInspector::displayName($claimedName);

        if ($this->config->denyExecutables && $this->inspector->isDangerous($claimedName, $type)) {
            throw UploadException::refused('dangerous');
        }

        if ($type === 'image/svg+xml' && ! $this->config->allowSvg) {
            throw UploadException::refused('svg');
        }

        if (! $rules->accepts($type)) {
            throw UploadException::refused('type', ['types' => implode(', ', $rules->types)]);
        }

        if ($this->config->verifyMimeTypes && ! $this->inspector->extensionMatches($claimedName, $type)) {
            throw UploadException::refused('mismatch');
        }

        $image = null;

        if (in_array($type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $image = $this->inspector->imageSize($path);

            if ($image === null) {
                throw UploadException::refused('corrupt');
            }

            if ($this->config->maxPixels < $image['width'] * $image['height']) {
                throw UploadException::refused('pixels');
            }

            $limits = $rules->dimensions;

            if (($limits['min_width'] !== null && $image['width'] < $limits['min_width'])
                || ($limits['min_height'] !== null && $image['height'] < $limits['min_height'])
                || ($limits['max_width'] !== null && $image['width'] > $limits['max_width'])
                || ($limits['max_height'] !== null && $image['height'] > $limits['max_height'])) {
                throw UploadException::refused('dimensions', ['width' => $image['width'], 'height' => $image['height']]);
            }
        }

        return new InspectedFile($path, $name, $type, $this->inspector->storageExtension($type), $size, $image);
    }
}
