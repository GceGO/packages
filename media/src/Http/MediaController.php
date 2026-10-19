<?php

declare(strict_types=1);

namespace Gcego\Media\Http;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Downloads\DownloadManager;
use Gcego\Media\Downloads\SignedMediaUrl;
use Gcego\Media\Exceptions\MediaAccessDeniedException;
use Gcego\Media\MediaConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET <prefix>/{media}, <prefix>/{media}/download and
 * <prefix>/{media}/variants/{variant}. {media} is a ULID looked up in the
 * viewer's tenant only. Signed links (signature, expiry, optional user
 * binding) are verified; the viewer is authorized unless the application
 * turned gcego-media.downloads.require_authorization off for valid
 * signed links.
 */
final readonly class MediaController
{
    public function __construct(private MediaAccess $access, private DownloadManager $downloads, private SignedMediaUrl $urls, private MediaConfig $config) {}

    public function show(Request $request, string $media): Response
    {
        return $this->serve($request, $media, inline: true);
    }

    public function download(Request $request, string $media): Response
    {
        return $this->serve($request, $media, inline: false);
    }

    public function variant(Request $request, string $media, string $variant): Response
    {
        return $this->serve($request, $media, inline: true, variant: $variant);
    }

    private function serve(Request $request, string $ulid, bool $inline, ?string $variant = null): Response
    {
        $user = $this->access->user();
        $signed = $request->query->has('signature');

        if ($signed && ! $this->urls->verify($request, $user instanceof Model ? $user : null)) {
            throw MediaAccessDeniedException::for('open');
        }

        // A valid signature vouches for the link's tenant; otherwise only the viewer's tenant is searched.
        $record = $signed ? $this->access->findSigned($ulid, (string) $request->query('t'), $this->urls) : $this->access->find($ulid);

        $trusted = $signed && ! $this->config->requireAuthorization && $this->access->available($record);

        return $this->downloads->download($record, $user, $inline, $variant, authorize: ! $trusted);
    }
}
