# Changelog

## 0.1.0 - 2026-10-17

First release.

- **Definitions:** `Media` (disk, visibility, types, size and count limits, single, dimensions, variants; immutable fluent API), `MediaCollection`, `Variant`, `Fit`, `MediaVisibility`, `MediaStatus`, `ScanStatus`, `MediaAbility`; `MediaCollectionRegistry` and `CollectionRules` (limits only tighten).
- **Records:** `MediaRecord` (ULID identity, hidden storage details, soft deletes), `MediaAttachment` (ordered, shared), the `HasMedia` trait, and migrations for `gcego_media` and `gcego_media_attachments`.
- **Uploads:** `UploadManager` and `UploadValidator`:
  - checks: content-detected types, name/content consistency, dangerous-file and SVG/HTML refusal, pixel and dimension limits;
  - temporary uploads bound to their uploader and tenant (`TemporaryUploads`);
  - storage: EXIF stripping, generated paths, compensating cleanup on database failure or rollback.
- **Storage:** `StoragePathResolver` (keyed tenant hashes, random names) and `MediaStorage` (allow-listed disks, managed paths only).
- **Processing:** `MediaProcessor` and the `ProcessMedia` job (scan, metadata, GD variants; idempotent, unique, retried). `ImageTransformer`, `MediaMetadataExtractor` (ffprobe optional), and the `MalwareScanner` contract with quarantine.
- **Access:** `MediaAccess` (tenant scope, availability, policy, signed-link tenant), `Contracts\MediaAccessPolicy` and `DefaultMediaAccessPolicy` (deny by default).
- **Downloads:** `DownloadManager` (safe headers, inline only for passive types, range requests, optional temporary URLs), `SignedMediaUrl` (expiring, user- and tenant-bound) and the `gcego.media.*` routes.
- **Lifecycle:** `MediaReplacer` (safe replacement, soft and permanent deletion), and the `gcego:media:cleanup` and `gcego:media:regenerate` commands.
- **Integrations:**
  - `Fields\MediaUpload` (gcego/forms, persisted with the record in the action transaction);
  - `Columns\MediaImage` and `Columns\MediaFile` (gcego/tables);
  - `Entries\MediaPreview` (gcego/infolists);
  - the `MediaLibrary`, `MediaBrowser` and `MediaUploader` Livewire components;
  - `PanelMediaExtension` (gcego/panels);
  - `MediaNotifier` (gcego/notifications).
- **Maintenance:** `MediaInspector` and `MediaReport`, presets, styling slots, and English and French translations.
