<?php

declare(strict_types=1);

namespace Gcego\Media\Downloads;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Exceptions\MediaNotFoundException;
use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Storage\MediaStorage;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves media after authorization: the original (inline only for types a
 * browser shows passively: raster images, PDF, audio, video, plain text;
 * everything else, SVG included, as a download) or a variant. Responses
 * carry nosniff, a restrictive Content-Security-Policy, no referrer, and
 * private no-store caching for private media. Local disks support range
 * requests (seeking in audio and video).
 */
final readonly class DownloadManager
{
    public const array InlineTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain', 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'video/mp4', 'video/webm', 'video/quicktime'];

    public function __construct(private MediaAccess $access, private MediaStorage $storage, private MediaConfig $config) {}

    /**
     * @throws MediaNotFoundException
     */
    public function download(MediaRecord $media, ?Authenticatable $user = null, bool $inline = false, ?string $variant = null, bool $authorize = true): Response
    {
        if ($authorize) {
            $this->access->authorize($inline ? MediaAbility::View : MediaAbility::Download, $media, user: $user);
        }

        [$path, $mime, $name] = $variant === null
            ? [$media->path, $media->mime, $media->original_name]
            : $this->variantFile($media, $variant);

        if (! $this->storage->exists($media->disk, $path)) {
            throw MediaNotFoundException::make();
        }

        $inline = $inline && in_array($mime, self::InlineTypes, true);
        $disposition = HeaderUtils::makeDisposition($inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $name, self::asciiName($name, $media->extension));

        if (! $inline && $this->config->temporaryUrls && $media->visibility === MediaVisibility::Private) {
            $url = $this->storage->temporaryUrl($media->disk, $path, now()->addMinutes(5), $name);

            if ($url !== null) {
                return $this->secure(new RedirectResponse($url), $media, 'application/octet-stream', $disposition);
            }
        }

        $filesystem = $this->storage->disk($media->disk);

        if ($filesystem instanceof FilesystemAdapter && $filesystem->getAdapter() instanceof LocalFilesystemAdapter) {
            $response = new BinaryFileResponse($filesystem->path($path), 200, [], $media->visibility === MediaVisibility::Public, null, false, false);
        } else {
            $stream = $this->storage->readStream($media->disk, $path) ?? throw MediaNotFoundException::make();
            $response = new StreamedResponse(static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            }, 200);
        }

        return $this->secure($response, $media, $mime, $disposition);
    }

    /**
     * @return array{string, string, string}
     *
     * @throws MediaNotFoundException
     */
    private function variantFile(MediaRecord $media, string $variant): array
    {
        $file = preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $variant) === 1 ? $media->variant($variant) : null;

        if ($file === null) {
            throw MediaNotFoundException::make();
        }

        $extension = $file['mime'] === 'image/jpeg' ? 'jpg' : substr($file['mime'], 6);

        return [$file['path'], $file['mime'], pathinfo($media->original_name, PATHINFO_FILENAME).'-'.$variant.'.'.$extension];
    }

    private function secure(Response $response, MediaRecord $media, string $mime, string $disposition): Response
    {
        $headers = $response->headers;
        $headers->set('Content-Type', in_array($mime, self::InlineTypes, true) ? $mime : 'application/octet-stream');
        $headers->set('Content-Disposition', $disposition);
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Content-Security-Policy', $mime === 'application/pdf' ? "default-src 'none'; object-src 'self'; frame-ancestors 'self'" : "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'; sandbox");
        $headers->set('Cache-Control', $media->visibility === MediaVisibility::Public ? 'public, max-age=86400' : 'private, no-store, max-age=0');

        return $response;
    }

    private static function asciiName(string $name, string $extension): string
    {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);

        return trim($ascii, '._ ') === '' ? 'file.'.$extension : $ascii;
    }
}
