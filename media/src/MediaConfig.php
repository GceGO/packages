<?php

declare(strict_types=1);

namespace Gcego\Media;

use Gcego\Media\Contracts\MalwareScanner;
use Gcego\Media\Contracts\MediaAccessPolicy;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Exceptions\InvalidMediaException;

/**
 * Validated gcego-media configuration (a container singleton, plain data).
 */
final readonly class MediaConfig
{
    /**
     * @param  list<string>  $disks
     * @param  list<string>  $allowedTypes
     * @param  array<string, array<string, mixed>>  $collections
     * @param  array<string, array<string, mixed>>  $defaultVariants
     * @param  list<string>  $downloadMiddleware
     * @param  class-string<MediaAccessPolicy>|null  $accessPolicy
     * @param  class-string<MalwareScanner>|null  $scanner
     * @param  list<string>  $keepUnattached
     */
    public function __construct(
        public string $disk = 'local',
        public array $disks = ['local', 'public'],
        public MediaVisibility $visibility = MediaVisibility::Private,
        public string $pathPrefix = 'gcego-media',
        public int $maxSize = 10240,
        public int $maxFiles = 10,
        public int $temporaryMinutes = 60,
        public array $allowedTypes = [],
        public string $defaultCollection = 'default',
        public array $collections = [],
        public int $maxPixels = 40_000_000,
        public bool $stripMetadata = true,
        public array $defaultVariants = [],
        public bool $transformations = true,
        public ?string $queue = 'media',
        public ?string $queueConnection = null,
        public int $tries = 3,
        public ?string $ffprobe = 'ffprobe',
        public int $probeTimeout = 15,
        public bool $requireAuthorization = true,
        public int $signedMinutes = 15,
        public string $routePrefix = 'media',
        public array $downloadMiddleware = ['web', 'gcego.tenant:optional'],
        public bool $temporaryUrls = false,
        public bool $verifyMimeTypes = true,
        public bool $enforceTenantScope = true,
        public bool $denyExecutables = true,
        public bool $allowSvg = false,
        public ?string $accessPolicy = null,
        public ?string $scanner = null,
        public string $scanFailure = 'quarantine',
        public bool $notifyProcessing = false,
        public bool $panels = true,
        public int $unattachedHours = 24,
        public array $keepUnattached = ['default', 'library'],
    ) {
        if (! in_array($disk, $disks, true)) {
            throw InvalidMediaException::invalid('gcego-media.disk', sprintf('"%s" must be one of gcego-media.disks.', $disk));
        }

        if (preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $pathPrefix) !== 1) {
            throw InvalidMediaException::invalid('gcego-media.path_prefix', 'use lowercase directory names such as "gcego-media" (no "..").');
        }

        foreach (['uploads.max_size' => [$maxSize, 1, 10_485_760], 'uploads.max_files' => [$maxFiles, 1, 500], 'uploads.temporary_expiration_minutes' => [$temporaryMinutes, 1, 10_080], 'downloads.signed_url_expiration_minutes' => [$signedMinutes, 1, 10_080], 'transformations.tries' => [$tries, 1, 20], 'metadata.timeout' => [$probeTimeout, 1, 300], 'images.max_pixels' => [$maxPixels, 10_000, 500_000_000], 'cleanup.unattached_after_hours' => [$unattachedHours, 1, 8760]] as $key => [$value, $min, $max]) {
            if ($value < $min || $value > $max) {
                throw InvalidMediaException::invalid('gcego-media.'.$key, sprintf('must be between %d and %d.', $min, $max));
            }
        }

        foreach ($allowedTypes as $type) {
            if (preg_match(Media::TypePattern, $type) !== 1) {
                throw InvalidMediaException::invalid('gcego-media.uploads.allowed_types', sprintf('"%s" is not a content type.', mb_substr($type, 0, 60)));
            }
        }

        if (preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $routePrefix) !== 1) {
            throw InvalidMediaException::invalid('gcego-media.downloads.prefix', 'use URL segments such as "media".');
        }

        if ($accessPolicy !== null && ! is_a($accessPolicy, MediaAccessPolicy::class, true)) {
            throw InvalidMediaException::invalid('gcego-media.security.access_policy', sprintf('%s does not implement %s.', $accessPolicy, MediaAccessPolicy::class));
        }

        if ($scanner !== null && ! is_a($scanner, MalwareScanner::class, true)) {
            throw InvalidMediaException::invalid('gcego-media.scanning.scanner', sprintf('%s does not implement %s.', $scanner, MalwareScanner::class));
        }

        if (! in_array($scanFailure, ['quarantine', 'allow'], true)) {
            throw InvalidMediaException::invalid('gcego-media.scanning.on_failure', 'use "quarantine" or "allow".');
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $get = static fn (string $key, mixed $default): mixed => data_get($config, $key, $default);
        $bool = static fn (string $key, bool $default): bool => is_bool($value = $get($key, $default)) ? $value : throw InvalidMediaException::invalid('gcego-media.'.$key, 'must be true or false.');
        $int = static fn (string $key, int $default): int => is_int($value = $get($key, $default)) ? $value : throw InvalidMediaException::invalid('gcego-media.'.$key, 'must be an integer.');
        $string = static fn (string $key, string $default): string => is_string($value = $get($key, $default)) && $value !== '' ? $value : throw InvalidMediaException::invalid('gcego-media.'.$key, 'must be a string.');
        $nullable = static fn (string $key, ?string $default): ?string => is_string($value = $get($key, $default)) || $value === null ? ($value === '' ? null : $value) : throw InvalidMediaException::invalid('gcego-media.'.$key, 'must be a string or null.');
        $strings = static function (string $key, array $default) use ($get): array {
            $value = $get($key, $default);

            if (! is_array($value) || array_filter($value, static fn (mixed $entry): bool => ! is_string($entry)) !== []) {
                throw InvalidMediaException::invalid('gcego-media.'.$key, 'must be a list of strings.');
            }

            /** @var list<string> */
            return array_values($value);
        };
        $maps = static function (string $key) use ($get): array {
            $value = $get($key, []);

            if (! is_array($value) || array_filter($value, static fn (mixed $entry, mixed $name): bool => ! is_string($name) || ! is_array($entry), ARRAY_FILTER_USE_BOTH) !== []) {
                throw InvalidMediaException::invalid('gcego-media.'.$key, 'must map names to settings arrays.');
            }

            /** @var array<string, array<string, mixed>> */
            return $value;
        };
        $policy = $nullable('security.access_policy', null);
        $scanner = $nullable('scanning.scanner', null);

        return new self(
            disk: $string('disk', 'local'),
            disks: $strings('disks', ['local', 'public']),
            visibility: MediaVisibility::tryFrom($string('visibility', 'private')) ?? throw InvalidMediaException::invalid('gcego-media.visibility', 'use "private" or "public".'),
            pathPrefix: $string('path_prefix', 'gcego-media'),
            maxSize: $int('uploads.max_size', 10240),
            maxFiles: $int('uploads.max_files', 10),
            temporaryMinutes: $int('uploads.temporary_expiration_minutes', 60),
            allowedTypes: $strings('uploads.allowed_types', []),
            defaultCollection: $string('collections.default', 'default'),
            collections: $maps('collections.definitions'),
            maxPixels: $int('images.max_pixels', 40_000_000),
            stripMetadata: $bool('images.strip_metadata', true),
            defaultVariants: $maps('images.default_variants'),
            transformations: $bool('transformations.enabled', true),
            queue: $nullable('transformations.queue', 'media'),
            queueConnection: $nullable('transformations.connection', null),
            tries: $int('transformations.tries', 3),
            ffprobe: $nullable('metadata.ffprobe', 'ffprobe'),
            probeTimeout: $int('metadata.timeout', 15),
            requireAuthorization: $bool('downloads.require_authorization', true),
            signedMinutes: $int('downloads.signed_url_expiration_minutes', 15),
            routePrefix: $string('downloads.prefix', 'media'),
            downloadMiddleware: $strings('downloads.middleware', ['web', 'gcego.tenant:optional']),
            temporaryUrls: $bool('downloads.temporary_urls', false),
            verifyMimeTypes: $bool('security.verify_mime_types', true),
            enforceTenantScope: $bool('security.enforce_tenant_scope', true),
            denyExecutables: $bool('security.deny_executable_uploads', true),
            allowSvg: $bool('security.allow_svg', false),
            accessPolicy: $policy === null ? null : (is_a($policy, MediaAccessPolicy::class, true) ? $policy : throw InvalidMediaException::invalid('gcego-media.security.access_policy', sprintf('%s does not implement %s.', $policy, MediaAccessPolicy::class))),
            scanner: $scanner === null ? null : (is_a($scanner, MalwareScanner::class, true) ? $scanner : throw InvalidMediaException::invalid('gcego-media.scanning.scanner', sprintf('%s does not implement %s.', $scanner, MalwareScanner::class))),
            scanFailure: $string('scanning.on_failure', 'quarantine'),
            notifyProcessing: $bool('notifications.processing', false),
            panels: $bool('panels.enabled', true),
            unattachedHours: $int('cleanup.unattached_after_hours', 24),
            keepUnattached: $strings('cleanup.keep_unattached', ['default', 'library']),
        );
    }
}
