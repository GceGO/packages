<?php

declare(strict_types=1);

namespace Gcego\Media\Downloads;

use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Keys;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Expiring, signed media URLs (Laravel's URL signing). A signed URL proves
 * the link was issued by the application and has not expired; it can also
 * be bound to one user ("u", a keyed hash). It carries its tenant as a
 * keyed hash ("t"), so a valid link finds tenant media without a session
 * tenant, and only media of that tenant. With
 * gcego-media.downloads.require_authorization (the default) the viewer is
 * still authorized on every request: a signed URL is never a substitute
 * for access checks.
 */
final readonly class SignedMediaUrl
{
    public const array Routes = ['show' => 'gcego.media.show', 'download' => 'gcego.media.download', 'variant' => 'gcego.media.variant'];

    public function __construct(private UrlGenerator $urls, private MediaConfig $config, private Repository $settings) {}

    /**
     * A plain route URL (authorization on every request).
     */
    public function route(MediaRecord $media, ?string $variant = null, bool $download = false): string
    {
        return $this->urls->route(self::name($variant, $download), self::parameters($media, $variant), false);
    }

    public function signed(MediaRecord $media, ?string $variant = null, bool $download = false, ?Model $for = null, ?int $minutes = null): string
    {
        $parameters = [...self::parameters($media, $variant), 't' => $this->tenantHash($media->tenant)];

        if ($for !== null) {
            $parameters['u'] = $this->userHash($for);
        }

        return $this->urls->temporarySignedRoute(self::name($variant, $download), now()->addMinutes($minutes ?? $this->config->signedMinutes), $parameters, false);
    }

    /**
     * Whether a signed request is valid: signature, expiry and, when bound, the user.
     */
    public function verify(Request $request, ?Model $user): bool
    {
        if (! $this->urls instanceof \Illuminate\Routing\UrlGenerator || ! $this->urls->hasValidSignature($request, false)) {
            return false;
        }

        $bound = $request->query('u');

        return $bound === null || ($user !== null && is_string($bound) && hash_equals($this->userHash($user), $bound));
    }

    /**
     * The signed tenant of a link: a keyed hash, so the URL reveals no tenant id.
     */
    public function tenantHash(?string $tenant): string
    {
        $key = $this->settings->get('app.key');

        return substr(hash_hmac('sha256', 'gcego-media-tenant|'.($tenant ?? ''), is_string($key) ? $key : ''), 0, 24);
    }

    public function userHash(Model $user): string
    {
        $key = $this->settings->get('app.key');

        return substr(hash_hmac('sha256', 'gcego-media-user|'.$user->getMorphClass().'|'.Keys::string($user->getKey()), is_string($key) ? $key : ''), 0, 32);
    }

    private static function name(?string $variant, bool $download): string
    {
        return $variant !== null ? self::Routes['variant'] : ($download ? self::Routes['download'] : self::Routes['show']);
    }

    /**
     * @return array<string, string>
     */
    private static function parameters(MediaRecord $media, ?string $variant): array
    {
        return $variant === null ? ['media' => $media->ulid] : ['media' => $media->ulid, 'variant' => $variant];
    }
}
