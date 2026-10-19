<?php

declare(strict_types=1);

namespace Gcego\Media\Access;

use Gcego\Media\Contracts\MediaAccessPolicy;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Definitions\ScanStatus;
use Gcego\Media\Downloads\SignedMediaUrl;
use Gcego\Media\Exceptions\MediaAccessDeniedException;
use Gcego\Media\Exceptions\MediaNotFoundException;
use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Every media decision goes through here:
 *
 * 1. tenant: media of another tenant does not exist for the viewer (404);
 * 2. availability: quarantined media, and media not yet scanned clean when
 *    a scanner is configured, is never viewed or downloaded;
 * 3. the access policy (default or configured), which denies on error.
 *
 * Lookups by ULID are always tenant-scoped, so a forged id finds nothing.
 */
final readonly class MediaAccess
{
    public function __construct(
        private Container $container,
        private MediaConfig $config,
        private TenantContext $tenants,
        private Auth $auth,
    ) {}

    public function user(): ?Authenticatable
    {
        $panel = class_exists(PanelContextResolver::class) ? $this->container->make(PanelContextResolver::class)->current() : null;

        return $panel !== null ? $panel->user : $this->auth->guard()->user();
    }

    public function tenant(): ?string
    {
        $tenant = $this->tenants->tenant();

        return $tenant === null ? null : (string) $tenant->key;
    }

    /**
     * @return Builder<MediaRecord>
     */
    public function query(): Builder
    {
        $query = MediaRecord::query();

        return $this->config->enforceTenantScope ? $query->inTenant($this->tenant()) : $query;
    }

    /**
     * @throws MediaNotFoundException
     */
    public function find(string $ulid): MediaRecord
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $ulid) !== 1) {
            throw MediaNotFoundException::make();
        }

        return $this->query()->where('ulid', strtolower($ulid))->first() ?? throw MediaNotFoundException::make();
    }

    /**
     * The media of a validly signed link: looked up by id, then required to
     * belong to the tenant the link was signed for.
     *
     * @throws MediaNotFoundException
     */
    public function findSigned(string $ulid, string $tenantHash, SignedMediaUrl $urls): MediaRecord
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $ulid) !== 1) {
            throw MediaNotFoundException::make();
        }

        $media = MediaRecord::query()->where('ulid', strtolower($ulid))->first();

        if ($media === null || ! hash_equals($urls->tenantHash($media->tenant), $tenantHash)) {
            throw MediaNotFoundException::make();
        }

        return $media;
    }

    /**
     * Whether the media's tenant is the viewer's (or the signed link's, when trusted).
     */
    public function inScope(MediaRecord $media): bool
    {
        return ! $this->config->enforceTenantScope || $media->tenant === $this->tenant();
    }

    public function allows(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null, ?Authenticatable $user = null): bool
    {
        $user ??= $this->user();

        if ($media !== null) {
            if ($this->config->enforceTenantScope && $media->tenant !== $this->tenant()) {
                return false;
            }

            if (in_array($ability, [MediaAbility::View, MediaAbility::Download], true) && ! $this->available($media)) {
                return false;
            }
        }

        try {
            return $this->policy()->allows($user, $ability, $media, $owner);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @throws MediaAccessDeniedException
     */
    public function authorize(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null, ?Authenticatable $user = null): void
    {
        if (! $this->allows($ability, $media, $owner, $user)) {
            throw MediaAccessDeniedException::for($ability->value);
        }
    }

    /**
     * Servable: not quarantined and, with a scanner, scanned clean (or failed scanning when allowed).
     */
    public function available(MediaRecord $media): bool
    {
        if (! $media->status->servable()) {
            return false;
        }

        if ($this->config->scanner === null) {
            return true;
        }

        return $media->scan_status === ScanStatus::Clean || ($media->scan_status === ScanStatus::Error && $this->config->scanFailure === 'allow');
    }

    private function policy(): MediaAccessPolicy
    {
        $policy = $this->container->make($this->config->accessPolicy ?? DefaultMediaAccessPolicy::class);

        return $policy instanceof MediaAccessPolicy ? $policy : $this->container->make(DefaultMediaAccessPolicy::class);
    }
}
