<?php

declare(strict_types=1);

namespace Gcego\Media\Metadata;

use Gcego\Media\MediaConfig;
use Gcego\Media\Transformations\ImageTransformer;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Safe, bounded metadata: image width, height, orientation and aspect
 * ratio (getimagesize and EXIF orientation only; no other EXIF data is
 * kept); audio and video duration, dimensions and codecs from ffprobe
 * when it is configured and installed (with a timeout). Unsupported
 * formats get the basics only; nothing here ever fails an upload.
 */
final readonly class MediaMetadataExtractor
{
    public function __construct(private MediaConfig $config) {}

    /**
     * @return array<string, string|int|float|null>
     */
    public function extract(string $path, string $type): array
    {
        $metadata = [];

        if (in_array($type, ImageTransformer::Readable, true)) {
            $size = @getimagesize($path);

            if (is_array($size) && $size[0] > 0 && $size[1] > 0) {
                $orientation = ImageTransformer::orientation($path, $type);
                [$width, $height] = $orientation >= 5 ? [$size[1], $size[0]] : [$size[0], $size[1]];
                $metadata = ['width' => $width, 'height' => $height, 'aspect_ratio' => round($width / $height, 4), 'orientation' => $width >= $height ? 'landscape' : 'portrait', 'format' => substr($type, 6)];
            }
        }

        if ((str_starts_with($type, 'video/') || str_starts_with($type, 'audio/')) && $this->probes()) {
            $metadata = [...$metadata, ...$this->probe($path)];
        }

        return $metadata;
    }

    public function probes(): bool
    {
        if ($this->config->ffprobe === null) {
            return false;
        }

        try {
            return Process::timeout(5)->run([$this->config->ffprobe, '-version'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string|int|float|null>
     */
    private function probe(string $path): array
    {
        try {
            $result = Process::timeout($this->config->probeTimeout)->run([(string) $this->config->ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
        } catch (Throwable) {
            return [];
        }

        $data = $result->successful() ? json_decode($result->output(), true) : null;

        if (! is_array($data)) {
            return [];
        }

        $metadata = [];
        $duration = is_array($data['format'] ?? null) ? ($data['format']['duration'] ?? null) : null;

        if (is_numeric($duration)) {
            $metadata['duration'] = round((float) $duration, 2);
        }

        foreach (is_array($data['streams'] ?? null) ? $data['streams'] : [] as $stream) {
            if (! is_array($stream)) {
                continue;
            }

            if (($stream['codec_type'] ?? null) === 'video' && ! isset($metadata['video_codec']) && (is_array($stream['disposition'] ?? null) ? ($stream['disposition']['attached_pic'] ?? 0) : 0) !== 1) {
                $metadata['video_codec'] = is_string($stream['codec_name'] ?? null) ? mb_substr($stream['codec_name'], 0, 32) : null;
                $metadata['width'] = is_int($stream['width'] ?? null) ? $stream['width'] : null;
                $metadata['height'] = is_int($stream['height'] ?? null) ? $stream['height'] : null;
            }

            if (($stream['codec_type'] ?? null) === 'audio' && ! isset($metadata['audio_codec'])) {
                $metadata['audio_codec'] = is_string($stream['codec_name'] ?? null) ? mb_substr($stream['codec_name'], 0, 32) : null;
            }
        }

        return $metadata;
    }
}
