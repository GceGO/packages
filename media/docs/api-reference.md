# API reference

| Class | Purpose |
| --- | --- |
| `Media` | base definition: `disk()`, `private()`, `public()`, `acceptedFileTypes()`, `image()`, `maxSize()`, `maxFiles()`, `single()`, `variants()`, `dimensions()` and readers |
| `MediaCollection` | a named `Media` (`collectionName()`) |
| `Definitions\Variant` | `make($name, $width, ?$height, Fit, $format, $quality)`, `fromArray()`, `signature()` |
| `Fields\MediaUpload` | gcego/forms field: `image()`, `acceptedFileTypes()`, `multiple()`, `maxFiles()`, `maxFileSize()`, `onDisk()`, `collection()`, `private()`, `public()`, `restrictions()`, `rules()` |
| `Columns\MediaImage`, `Columns\MediaFile` | gcego/tables columns |
| `Entries\MediaPreview` | gcego/infolists entry (`Limit`, `Players`) |
| `MediaManager` | `collection()`, `upload()`, `uploadMany()`, `attach()`, `detach()`, `media()`, `find()`, `allows()`, `url()`, `signedUrl()`, `download()`, `replace()`, `delete()`, `regenerate()`, `inspect()` |
| `Models\MediaRecord`, `Models\MediaAttachment`, `Models\HasMedia` | records and the owner trait (`gcegoMedia`, `mediaIn()`, `firstMedia()`) |
| `Uploads\UploadManager` | `upload()`, `uploadMany()`, `check()`, `store()`, `rules()`, `dispatch()` |
| `Uploads\UploadContext`, `UploadResult`, `InspectedFile`, `FileInspector`, `UploadValidator`, `TemporaryUploads` | upload pipeline |
| `Attachments\MediaAttachments`, `Attachments\MediaReplacer` | attach, detach, reorder, sync; replace, delete, purge |
| `Access\MediaAccess`, `Access\DefaultMediaAccessPolicy`, `Contracts\MediaAccessPolicy` | authorization |
| `Downloads\DownloadManager`, `Downloads\SignedMediaUrl` | serving and links |
| `Transformations\MediaProcessor`, `ImageTransformer`, `Jobs\ProcessMedia`, `Metadata\MediaMetadataExtractor` | processing |
| `Contracts\MalwareScanner`, `Scanning\ScanResult` | scanning |
| `Livewire\MediaLibrary`, `MediaBrowser`, `MediaUploader` | `gcego-media-library`, `gcego-media-browser`, `gcego-media-uploader` |
| `Diagnostics\MediaInspector` | `inspect(): MediaReport` |
| `Exceptions\*` | `InvalidMediaException`, `UploadException` (`reason`, `errors`), `MediaAccessDeniedException` (403), `MediaNotFoundException` (404), `MediaProcessingException`; all implement `MediaException` |
