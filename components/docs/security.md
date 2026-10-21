# Security model

Component definitions are application code. Component and builder class names
come from trusted application or package declarations; this package does not map
request-provided class names into an authorization policy. Applications authorize
access to their interfaces and data before serving them.

The manager verifies concrete component and builder classes before construction.
It checks that container-provided builders target the requested component and
that builder results have the requested type. These checks catch configuration
mistakes; they do not sandbox custom PHP code.

Properties accept only supported values. Array references are detached when
stored, and property arrays are limited to 32 nesting levels. Nested builder item
lists have the same depth limit. Tree traversal rejects ancestor cycles and,
by default, depths beyond 256. Normalization has a separate value-depth limit.
These depth limits do not impose a maximum total tree or collection size.

Runtime values remain opaque during normalization and are replaced by
placeholders, including in schema defaults and direct schema array exports.
Direct runtime property values bypass property normalizer callbacks. Value objects
normalize recursively; unsupported output is rejected. Custom rules, normalizers and component
constructors are trusted application code. Normalization is not HTML escaping or
output sanitization; rendering packages handle escaping for their output context.

The required service provider owns service registration. Facade resolution uses
the active application's scoped manager. Existing application overrides are
preserved, and builders and root-specific trees receive independent instances.

Release archives exclude local environment files, development directories,
dependencies and test artifacts. Documentation is included. Run the following
checks before publishing a release:

```bash
composer validate --strict
composer test
composer analyse
vendor/bin/pint --test
composer audit --locked
composer archive
```

A passing dependency audit reports only the advisories known at the time of the
check; it is not a guarantee that application code or all dependencies are free
of vulnerabilities.

Schema caches are scoped rather than process-static so closures and defaults do
not carry a previous application's request context forward. Validation caches
are tied to that scope through weak references. Retained managers cannot operate
on an inactive application or scope, cannot be serialized, and redact container
contents from debug output.

Anonymous PHP class names contain source filenames internally. Exported class
metadata and diagnostics remove that source suffix. This does not change explicit
application strings, raw property values or schema accessors used at runtime;
use normalization and schema array exports when producing metadata for transport.

## Catalog and examples

Catalog aliases resolve trusted application classes, not arbitrary request class
names. The provider-scoped catalog resets with each request/job scope. Register
application extensions using provider callbacks so the configuration is restored
on later scopes.

Form settings, URLs, rich text, table records and button action names are
configuration data. They do not implement authorization, file handling, request
validation or safe HTML rendering. Ordinary strings and records remain present
in normalized output and bounded diagnostics; avoid embedding secrets in layout
properties. Example records and flags are demonstration data.

The [runnable examples](usage.md) validate declarations before export. `composer check`
also checks strict PSR-4 autoloading and ambiguous class declarations; examples
are exercised by integration tests.
