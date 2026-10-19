<?php

declare(strict_types=1);

namespace Gcego\Media\Storage;

use Gcego\Media\Exceptions\InvalidMediaException;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaConfig;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Throwable;

/**
 * gcego/media's only access to files: Laravel's filesystem disks, limited
 * to gcego-media.disks and to paths StoragePathResolver generated.
 */
final readonly class MediaStorage
{
    public function __construct(private Factory $filesystems, private MediaConfig $config, private StoragePathResolver $paths) {}

    public function disk(string $disk): Filesystem
    {
        if (! in_array($disk, $this->config->disks, true)) {
            throw InvalidMediaException::invalid('Disk "'.$disk.'"', 'it is not one of gcego-media.disks.');
        }

        return $this->filesystems->disk($disk);
    }

    /**
     * Copy a local file to a generated path, streaming it.
     *
     * @throws UploadException when the disk refuses it
     */
    public function putFile(string $disk, string $path, string $localPath, string $visibility, string $mime): void
    {
        $this->guard($path);
        $stream = @fopen($localPath, 'rb');

        if ($stream === false) {
            throw UploadException::refused('unreadable');
        }

        try {
            $stored = $this->disk($disk)->writeStream($path, $stream, ['visibility' => $visibility, 'mimetype' => $mime, 'ContentType' => $mime]);
        } catch (Throwable $exception) {
            report($exception);
            $stored = false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($stored === false) {
            throw UploadException::refused('storage');
        }
    }

    public function putContents(string $disk, string $path, string $contents, string $visibility, string $mime): bool
    {
        $this->guard($path);

        try {
            return $this->disk($disk)->put($path, $contents, ['visibility' => $visibility, 'mimetype' => $mime, 'ContentType' => $mime]) !== false;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function exists(string $disk, string $path): bool
    {
        $this->guard($path);

        try {
            return $this->disk($disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return resource|null
     */
    public function readStream(string $disk, string $path)
    {
        $this->guard($path);

        try {
            $stream = $this->disk($disk)->readStream($path);
        } catch (Throwable) {
            return null;
        }

        return is_resource($stream) ? $stream : null;
    }

    /**
     * A local copy of a stored file for processing (the caller deletes it).
     */
    public function localCopy(string $disk, string $path): ?string
    {
        $stream = $this->readStream($disk, $path);

        if ($stream === null) {
            return null;
        }

        $local = tempnam(sys_get_temp_dir(), 'gcego-media-');

        if ($local === false) {
            fclose($stream);

            return null;
        }

        $target = fopen($local, 'wb');

        if ($target === false) {
            fclose($stream);

            return null;
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $local;
    }

    public function delete(string $disk, string ...$paths): bool
    {
        foreach ($paths as $path) {
            $this->guard($path);
        }

        try {
            return $paths === [] || $this->disk($disk)->delete($paths);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function copy(string $disk, string $from, string $to): bool
    {
        $this->guard($from);
        $this->guard($to);

        try {
            return $this->disk($disk)->copy($from, $to);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function move(string $disk, string $from, string $to): bool
    {
        $this->guard($from);
        $this->guard($to);

        try {
            return $this->disk($disk)->move($from, $to);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * The disk's public URL (public media only).
     */
    public function publicUrl(string $disk, string $path): ?string
    {
        $this->guard($path);
        $filesystem = $this->disk($disk);

        try {
            return $filesystem instanceof FilesystemAdapter ? $filesystem->url($path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A short-lived provider URL (S3) when the disk supports it.
     */
    public function temporaryUrl(string $disk, string $path, \DateTimeInterface $expires, string $filename): ?string
    {
        $this->guard($path);
        $filesystem = $this->disk($disk);

        if (! $filesystem instanceof FilesystemAdapter || ! $filesystem->providesTemporaryUrls()) {
            return null;
        }

        try {
            return $filesystem->temporaryUrl($path, $expires, ['ResponseContentDisposition' => 'attachment; filename="'.addcslashes($filename, '"\\').'"']);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Managed paths only (no "..", only generated characters).
     */
    private function guard(string $path): void
    {
        if (! $this->paths->isManaged($path)) {
            throw InvalidMediaException::invalid('The storage path', 'gcego/media only touches paths it generated.');
        }
    }
}
