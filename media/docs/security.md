# Security

## Threats and defenses

| Threat | Defense |
| --- | --- |
| A script uploaded as an image (`avatar.jpg` holding PHP) | the type comes from the bytes (`finfo`); PHP and other executables are refused |
| Name tricks (`invoice.php.png`, `.htaccess`, `../../x`) | every name segment is checked; names are display-only; paths are generated |
| Active content (HTML, SVG with scripts, XML) | refused by type (HTML is detected even inside `.txt`); SVG only with `allow_svg`, and then a download |
| MIME spoofing | the browser's type is ignored; the extension must fit the detected type |
| Decompression bombs | the pixel budget is checked before decoding; GD only decodes raster formats |
| A replayed or stolen temporary upload | Livewire's signed reference plus gcego/media's binding to the uploader and tenant, expiry, single use |
| Guessing private URLs | private files are never on a public URL; ids are ULIDs; paths are hidden from models (`$hidden`) |
| Another tenant's files | every lookup is tenant-scoped (404); signed links carry a keyed tenant hash |
| Forged ids in forms | a form may only keep media already attached to its record |
| Signed links shared too widely | they expire, can be user-bound, and still authorize the viewer by default |
| A browser running a downloaded file | `nosniff`, a restrictive CSP (sandbox), attachment disposition for non-passive types |
| EXIF leaks (GPS) | JPEG and WebP originals are re-encoded without metadata; variants never carry any |
| Infected files | an optional `MalwareScanner`; quarantined files are never served |
| Orphans after failures | storage is compensated on database failure and on surrounding transaction rollback |

## Deny by default

- Without a user, only public media may be viewed.
- Viewing private media needs one of: uploading it, viewing a record it is attached to, or `gcego-media.manage`.
- Attaching needs the owner's `update`.
- A record without a Laravel policy grants nothing through its attachments.
- An exception in a policy denies.
