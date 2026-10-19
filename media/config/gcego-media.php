<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Media
|--------------------------------------------------------------------------
|
| Uploads are untrusted: their type is detected from their content, their
| names are never used as paths, and media is private by default, served
| only through authorized (optionally signed) routes. Files live on your
| Laravel disks; records in gcego_media and gcego_media_attachments.
|
*/

return [

    // The default disk, and every disk media may use (anything else is refused).
    'disk' => 'local',
    'disks' => ['local', 'public'],

    // private or public. Public media must also be on a disk with a public URL.
    'visibility' => 'private',

    // Directory of media files on their disk: <prefix>/<tenant hash>/<collection>/<yyyy>/<mm>/<ulid>/...
    'path_prefix' => 'gcego-media',

    'uploads' => [
        // Kilobytes per file (collections and fields may only lower it).
        'max_size' => 10240,
        'max_files' => 10,
        // How long an unsaved upload stays usable, and only by the user who uploaded it.
        'temporary_expiration_minutes' => 60,
        // Content types accepted when a collection or field names none.
        'allowed_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain', 'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4',
            'video/mp4', 'video/webm', 'video/quicktime',
        ],
    ],

    'collections' => [
        // The collection of uploads that name none.
        'default' => 'default',
        // name => ['disk' => ..., 'visibility' => ..., 'types' => [...], 'max_size' => KB, 'max_files' => n, 'single' => bool,
        //          'variants' => ['thumb' => ['width' => 320, 'height' => 320, 'fit' => 'cover', 'format' => 'webp']]]
        'definitions' => [],
    ],

    'images' => [
        // Images larger than this many pixels are refused (decompression-bomb protection).
        'max_pixels' => 40_000_000,
        // Re-encode uploaded JPEG/WebP images without their EXIF data (GPS, device), after applying the orientation.
        'strip_metadata' => true,
        // Variants of image collections that declare none.
        'default_variants' => [
            'thumb' => ['width' => 320, 'height' => 320, 'fit' => 'cover', 'format' => 'webp', 'quality' => 80],
        ],
    ],

    'transformations' => [
        'enabled' => true,
        // Queue metadata and variant processing (null connection: the application default).
        'queue' => 'media',
        'connection' => null,
        'tries' => 3,
    ],

    'metadata' => [
        // ffprobe binary for audio/video duration and dimensions (null: skip; uploads never need it).
        'ffprobe' => 'ffprobe',
        'timeout' => 15,
    ],

    'downloads' => [
        'require_authorization' => true,
        'signed_url_expiration_minutes' => 15,
        // URL prefix and middleware of the media routes.
        'prefix' => 'media',
        // gcego.tenant (gcego/security) resolves the tenant, so tenant media is found (it is optional: tenant-less media works too).
        'middleware' => ['web', 'gcego.tenant:optional'],
        // Redirect authorized downloads of private files to the disk's temporary URL when it has one (S3).
        'temporary_urls' => false,
    ],

    'security' => [
        'verify_mime_types' => true,
        'enforce_tenant_scope' => true,
        'deny_executable_uploads' => true,
        // SVG can carry scripts; when allowed it is always served as a download.
        'allow_svg' => false,
        // A class implementing Gcego\Media\Contracts\MediaAccessPolicy (null: the default policy).
        'access_policy' => null,
    ],

    'scanning' => [
        // A class implementing Gcego\Media\Contracts\MalwareScanner (null: no scanning; files are "unscanned", never "clean").
        'scanner' => null,
        // When the scanner fails: quarantine (keep the file unavailable) or allow.
        'on_failure' => 'quarantine',
    ],

    'notifications' => [
        // Notify the uploader (gcego/notifications) when processing finishes or fails.
        'processing' => false,
    ],

    'panels' => [
        // Add the media library to every gcego/panels panel at "<panel>/media".
        'enabled' => true,
    ],

    'cleanup' => [
        // Unattached media older than this many hours may be removed by gcego:media:cleanup.
        'unattached_after_hours' => 24,
        // Collections whose unattached media is kept (library uploads are unattached on purpose).
        'keep_unattached' => ['default', 'library'],
    ],

];
