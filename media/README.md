# gcego/media

Media for GCEGO Admin Studio: secure uploads, collections, model attachments, private storage on your Laravel disks, authorized and signed downloads, image variants, metadata, a media library and browser, and malware-scanner hooks. It integrates with forms, tables, infolists, actions, notifications and panels.

```php
use Gcego\Media\Fields\MediaUpload;

new Form(
    new TextInput('title', 'Title'),
    (new MediaUpload('cover_image', 'Cover Image'))->image()->maxFileSize(5120),
    (new MediaUpload('attachments', 'Attachments'))->multiple()->maxFiles(10),
);
```

Saving the form through its Create or Edit action validates each file by its content and stores it privately under a generated name. It is attached to the record inside the action's transaction, and shown through authorized routes.

**Uploaded files are untrusted:**

- their type comes from their bytes, never from the browser;
- their names never reach a storage path;
- private media (the default) is only ever served after authorization.

| Concern | Who does it |
| --- | --- |
| Files | Laravel Filesystem disks |
| Background work | Laravel's queue |
| Image processing | PHP's GD |
| Audio and video metadata | ffprobe, when installed (optional) |
| Tenancy | gcego/security |
| Form, table and infolist rendering | gcego/forms, gcego/tables, gcego/infolists |
| Saving and transactions | gcego/actions |
| Library in each panel | gcego/panels |

## Contents

- [Requirements and installation](#requirements-and-installation)
- [Examples](#examples)
- [Forms: MediaUpload](#forms-mediaupload)
- [Collections and rules](#collections-and-rules)
- [Attachments](#attachments)
- [Upload security](#upload-security)
- [Storage, visibility and paths](#storage-visibility-and-paths)
- [Downloads and signed URLs](#downloads-and-signed-urls)
- [Access and tenancy](#access-and-tenancy)
- [Processing: metadata, variants, scanning](#processing-metadata-variants-scanning)
- [Tables, infolists, library and browser](#tables-infolists-library-and-browser)
- [Replacement, deletion and cleanup](#replacement-deletion-and-cleanup)
- [Notifications, panels and diagnostics](#notifications-panels-and-diagnostics)
- [Accessibility](#accessibility)
- [Testing and troubleshooting](#testing-and-troubleshooting)

## Requirements and installation

- PHP 8.3+ with `fileinfo`; GCEGO packages 01–18 and gcego/notifications.
- Optional: `gd` (variants, metadata stripping), `exif` (orientation), `ffprobe` (audio and video metadata), `league/flysystem-aws-s3-v3` (S3 disks).

```bash
composer require gcego/media
php artisan migrate                                       # gcego_media and gcego_media_attachments
php artisan vendor:publish --tag=gcego-media-config        # optional
```

Add the `HasMedia` trait to models that own media:

```php
use Gcego\Media\Models\HasMedia;

final class Lesson extends Model
{
    use HasMedia;
}
```

The tables are separate from other media packages' tables (spatie/laravel-medialibrary's `media` table is untouched). Media processing is queued on the `media` queue, so run a worker.

## Examples

<!-- example:fields -->
```php
return [
    new MediaUpload('image', 'Image'),
    (new MediaUpload('attachments', 'Attachments'))->multiple()->maxFiles(10),
    (new MediaUpload('documents', 'Documents'))->private()->acceptedFileTypes(['application/pdf']),
    new MediaUpload(
        new Property(MediaUpload::Name, 'document'),
        new Property(MediaUpload::Label, 'Document'),
        new Property(MediaUpload::Required, true),
        new Property(MediaUpload::MaxSize, 10240),
    ),
];
```

<!-- example:collection -->
```php
return (new MediaCollection('lesson-resources'))
    ->disk('local')
    ->private()
    ->maxFiles(20)
    ->variants(Variant::make('thumb', 320, 320, Fit::Cover));
```

<!-- example:attach -->
```php
$mediaManager = app(MediaManager::class);

$media = $mediaManager->attach(
    model: $lesson,
    file: $uploadedFile,
    collection: 'lesson-resources',
);

return $mediaManager->download(media: $media, user: auth()->user());
```

`new X(...)->method()` without the outer parentheses works on PHP 8.4+. The examples use `(new X(...))->method()` so they also run on PHP 8.3.

## Forms: MediaUpload

`MediaUpload` extends gcego/forms' `FileUpload`, so it reuses the form's Livewire temporary uploads, progress events and validation. The form value is a list of media ULIDs: the files already attached, plus the new uploads. Only media already attached to the record may be kept, so a submitted id can never point at somebody else's file.

| Method / property | Meaning |
| --- | --- |
| `image()` / `acceptedFileTypes([...])` | accepted content types (`MediaUpload::Types`), checked against the file's bytes |
| `multiple()`, `maxFiles(n)` | several files (otherwise single: a new file replaces the old one) |
| `maxFileSize(kb)` / `MediaUpload::MaxSize` | KB per file (never above `gcego-media.uploads.max_size`) |
| `onDisk('public')`, `private()`, `public()` | storage disk (one of `gcego-media.disks`) and visibility |
| `collection('name')` | the media collection (default: the field name) |

Two names differ from the original prompt. gcego/forms' `FileUpload` already has `maxSize(FormsConfig): int` and `disk(FormsConfig): string` readers, so the fluent setters on the field are `maxFileSize()` and `onDisk()`. Collections use `maxSize()` and `disk()`.

Saving goes like this:

1. **Validation:** after the form's validation, `stage()` checks every file's content, ownership and count, and every field's problems are reported together.
2. **Persisting:** after the record is saved, inside the gcego/actions transaction, `persist()` stores the new files, attaches them and detaches what the user removed. Detached media is kept: it may be shared.
3. **Failures:** if anything fails, the record rolls back and stored files are removed.

This uses gcego/forms' `PersistsWithRecord` contract and gcego/actions' `ValidatedInput::$afterSave`, both added for this package. A standalone form without an action receives only attribute data.

## Collections and rules

The rules that apply to an upload tighten in three steps:

1. `gcego-media` configuration;
2. the collection;
3. the field.

Sizes and counts take the smallest value, and types take the intersection.

```php
app(MediaManager::class)->collection((new MediaCollection('avatar'))->image()->single()->dimensions(minWidth: 128));
```

You can also declare collections in `gcego-media.collections.definitions`. A collection that is not registered gets the default rules under its name.

## Attachments

```php
$manager = app(MediaManager::class);
$manager->attach($lesson, $file, 'lesson-resources');      // UploadedFile, local path (trusted) or existing MediaRecord
$manager->media($lesson, 'lesson-resources');              // in order
$manager->detach($lesson, $media, 'lesson-resources');     // the media itself is kept
$lesson->mediaIn('lesson-resources');  $lesson->firstMedia('cover');
```

How attachments behave:

- They are ordered (`MediaAttachments::reorder()`).
- Single collections replace their file.
- Collections refuse more files than `maxFiles`.
- One media record may be attached to several records (shared).
- Attaching and detaching need the owner record's `update` ability (its Laravel policy).

## Upload security

| Check | How |
| --- | --- |
| Type | `finfo` on the content; the browser's type is ignored |
| Name vs content | the extension must fit the detected type (`photo.pdf` holding a PNG is refused) |
| Dangerous files | executables, scripts, PHP, HTML (also renamed as `.txt`), XML and double extensions (`invoice.php.png`) are refused |
| SVG | refused unless `security.allow_svg`, and then always served as a download |
| Images | must decode; larger than `images.max_pixels` is refused (decompression bombs); optional dimension limits |
| Size and count | per file and per collection |
| Names | shown sanitized (no paths, control or bidirectional characters); never used in paths |
| Temporary uploads | Livewire's signed temporary files are also bound to their uploader and tenant, expire after `uploads.temporary_expiration_minutes`, and are usable once |
| Privacy | JPEG and WebP originals are re-encoded without EXIF data (GPS, device) after applying their orientation (`images.strip_metadata`) |

## Storage, visibility and paths

Files live on Laravel disks listed in `gcego-media.disks`: local, public, S3-compatible and any other Laravel driver.

Their paths look like this:

```
gcego-media/<keyed tenant hash>/<collection>/<yyyy>/<mm>/<ulid>/<random>.<ext>     (variants: .../<ulid>/variants/<name>.<ext>)
```

Every segment is generated, and the tenant appears only as a keyed hash.

Private media, the default, is only served through the authorized routes. Public media on a public disk uses the disk's URL. gcego/media only touches paths it generated.

## Downloads and signed URLs

| Route | Purpose |
| --- | --- |
| `GET /media/{ulid}` (`gcego.media.show`) | inline for raster images, PDF, audio, video and plain text; a download otherwise |
| `GET /media/{ulid}/download` (`gcego.media.download`) | always a download |
| `GET /media/{ulid}/variants/{name}` (`gcego.media.variant`) | an image variant |

All three run `downloads.middleware` (`web`, `gcego.tenant:optional`). Every response sends:

- `X-Content-Type-Options: nosniff`;
- a restrictive `Content-Security-Policy` (sandboxed except for PDF);
- `Referrer-Policy: no-referrer`;
- private `no-store` caching for private media;
- an RFC 6266 `Content-Disposition`.

Local disks support range requests. With `downloads.temporary_urls`, private downloads from S3 redirect to a short-lived provider URL after authorization.

```php
$url = app(MediaManager::class)->signedUrl($media, minutes: 15, for: $user);   // expiring, signed, optionally bound to a user
```

A signed link proves that the application issued it and that it has not expired. It carries a keyed tenant hash and can be bound to one user. With `downloads.require_authorization` (the default), the viewer is still authorized on every request. Turn that off only for links meant to be shared.

## Access and tenancy

`MediaAccess` makes every decision:

1. **Tenant:** media is looked up in the viewer's tenant only, so another institution's file is a 404.
2. **Availability:** quarantined media, and media not yet scanned clean when a scanner is configured, is never served.
3. **Policy:** the access policy decides last. It denies on errors.

The default policy, `DefaultMediaAccessPolicy`, denies by default:

| Ability | Who |
| --- | --- |
| everything | users the `gcego-media.manage` Gate ability allows |
| upload | signed-in users |
| view and download | public media; media you uploaded; media attached to a record you may `view` |
| attach | users who may `update` the owner record |
| replace and delete | the uploader (plus `update` on the owner, when given) |

Bind your own `Contracts\MediaAccessPolicy` with `security.access_policy`.

## Processing: metadata, variants, scanning

The `ProcessMedia` job runs after the upload commits. It is unique per media, retried with backoff, and carries only the media id. It does three things:

- **Scanning:** the malware scan runs first, through `Contracts\MalwareScanner`, when one is configured.
- **Metadata:** images get width, height, aspect ratio, orientation and format; audio and video get duration, dimensions and codecs via ffprobe, with a timeout.
- **Variants:** generated with GD, using cover, contain, fill or width fits, jpg, png or webp output and a quality setting. Collections that declare no variants get `images.default_variants` (a 320 px webp `thumb`).

Processing is idempotent:

- unchanged variants are kept;
- changed definitions regenerate;
- a record already being processed is skipped.

Failures mark the media `failed`, with a short reason, and are retried. The original is never modified.

Without a scanner, files are `unscanned`, never `clean`. With one, infected files and files the scanner could not check are `quarantined`, unless `scanning.on_failure` is `allow`.

## Tables, infolists, library and browser

```php
new MediaImage('cover', 'Cover');                 // gcego/tables: thumbnail of the first media (eager-loaded, no query per row)
new MediaFile('attachments', 'Files');            // first file's name, type and size, a download link, "+n more"
new MediaPreview('attachments', 'Attachments');   // gcego/infolists: images, PDF, audio and video players, metadata, downloads
```

| Livewire component | What it does |
| --- | --- |
| `<livewire:gcego-media-library />` | the viewer's media (all of the tenant's for media managers): grid or list, search, type/collection filters, sorting, server-side pagination, details, downloads, uploads into the `library` collection, bulk delete |
| `<livewire:gcego-media-browser :owner="$lesson" collection="lesson-resources" />` | pick library media and attach it; the record and collection are `#[Locked]` |
| `<livewire:gcego-media-uploader collection="..." :owner="$lesson" />` | a standalone uploader with per-file results |

## Replacement, deletion and cleanup

```php
$manager->replace($media, $newFile);         // validated and stored first; the old file and variants are deleted after the commit
$manager->delete($media);                    // soft delete (files kept)
$manager->delete($media, permanent: true);   // files, variants, attachments and record
```

```bash
php artisan gcego:media:cleanup [--hours=24] [--dry-run] [--files]
php artisan gcego:media:regenerate [--collection=] [--failed]
```

The cleanup command:

- **purges** soft-deleted media, and unattached media outside `cleanup.keep_unattached` (`default` and `library`), older than `--hours`;
- **never touches** attached or shared media;
- with `--files`, **lists** files that have no record, and never deletes them.

## Notifications, panels and diagnostics

- **Notifications:** `notifications.processing` sends the uploader a gcego/notifications message when processing finishes or fails. It carries the file's name only, never its content. It is off by default.
- **Panels:** the library is at `<panel>/media` (route `<namespace>media`) in every gcego/panels panel, behind the panel's checks.
- **Diagnostics:** `app(MediaManager::class)->inspect()` reports on:
  - disks (configured, reachable, public);
  - PHP upload limits versus `uploads.max_size`;
  - the tables and routes;
  - the queue, GD, ffprobe and the scanner;
  - signed-link and tenancy settings;
  - processing failures.

  It never includes file contents, paths or credentials.

## Accessibility

| Area | Behaviour |
| --- | --- |
| Choosing files | every upload is a labelled native file input (keyboard and screen readers); drag and drop is optional on top of it |
| Upload progress | real Livewire progress in a `progressbar` with `aria-valuenow`, announced in a polite live region, with cancel |
| Errors | `role="alert"`, naming the file |
| Library and browser | labelled checkboxes and remove buttons ("Remove syllabus.pdf"), a labelled search landmark, `aria-pressed` view toggles, a focus-receiving details panel, a labelled pagination `nav` |
| Previews | alt text from the media (decorative thumbnails have `alt=""`); players have accessible names |
| Visuals | focus rings are visible, motion respects `prefers-reduced-motion`, and colors are theme tokens |

## Testing and troubleshooting

```bash
composer test       # Pest: uploads, security, downloads, processing (GD, ffprobe when installed), forms, tables, Livewire
composer analyse    # PHPStan (max)
composer format     # Pint
```

| Symptom | Check |
| --- | --- |
| "This upload expired or belongs to someone else" | the temporary file is older than `uploads.temporary_expiration_minutes`, or another user's |
| 404 on a media URL | another tenant's media, a soft-deleted file, or the route needs `gcego.tenant` (default middleware) |
| No thumbnails | GD installed? A queue worker for the `media` queue? `inspect()` codes `images.gd`, `queue.sync` |
| Large uploads fail | `inspect()` codes `uploads.php_limit` and `uploads.post_limit` |

See [docs/](docs) for the architecture and the API reference.
