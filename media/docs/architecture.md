# Architecture

```
MediaUpload (forms) ─ stage(): content checks ─► action transaction ─ persist(): UploadManager::store + MediaAttachments
MediaManager::attach/upload ──────────────────────────────────────────► UploadManager
UploadManager: authorize ─ TemporaryUploads ─ UploadValidator(FileInspector) ─ strip EXIF ─ MediaStorage::putFile
               ─ transaction(MediaRecord [+ MediaAttachment]) ─ compensate on failure/rollback ─ ProcessMedia (after commit)
ProcessMedia ─► MediaProcessor: claim ─ scan (MalwareScanner) ─ metadata ─ variants (ImageTransformer) ─ ready | failed | quarantined
GET /media/{ulid}[/download|/variants/{v}] ─► MediaController ─ signed? verify ─ MediaAccess (tenant, availability, policy) ─ DownloadManager
```

| Piece | Lifetime | Notes |
| --- | --- | --- |
| `Media`, `MediaCollection`, `Variant`, `MediaUpload` | values | immutable; no storage or database access when built |
| `MediaConfig`, `MediaCollectionRegistry`, `MediaStorage`, `StoragePathResolver`, `FileInspector`, `UploadValidator`, `ImageTransformer`, `SignedMediaUrl` | singletons | stateless |
| `MediaAccess`, `UploadManager`, `TemporaryUploads`, `MediaAttachments`, `MediaReplacer`, `DownloadManager`, `MediaProcessor`, `MediaLibraryQuery` | scoped | read the current user and tenant (reset per request and job) |

## What it reuses

| Need | Reused |
| --- | --- |
| files | Laravel Filesystem disks (local, public, S3, ...) |
| uploads in the browser | Livewire temporary uploads (signed references, progress events) |
| form validation and persistence | gcego/forms (`FileUpload`, `PersistsWithRecord`) and gcego/actions (`ValidatedInput::$afterSave`) |
| background work | Laravel queue (`ShouldBeUnique`, after commit, retries) |
| signatures | Laravel URL signing |
| tenancy | gcego/security `TenantContext` and the `gcego.tenant` middleware |
| record authorization | Laravel policies of the owner records |
| UI | gcego/tables columns, gcego/infolists entries, gcego/panels extension, gcego/chroma classes and icons |

## Extension points

| Contract | For |
| --- | --- |
| `Contracts\MediaAccessPolicy` | custom access rules (gcego/settings could provide per-tenant rules) |
| `Contracts\MalwareScanner` | ClamAV or a cloud scanner |
| `MediaManager::collection()` | packages registering collections (gcego/workflows attachments) |
| `MediaInspector` / `MediaReport::toArray()` | gcego/cli health commands |
| `UploadResult`, `MediaRecord` statuses | gcego/testing assertions, gcego/analytics storage reports |

gcego/studio did not exist when this package was built, so there is no Studio integration yet. Studio can use the contracts, the package descriptor (`media.*` capabilities) and the inspector.
