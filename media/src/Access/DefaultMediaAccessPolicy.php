<?php

declare(strict_types=1);

namespace Gcego\Media\Access;

use Gcego\Media\Contracts\MediaAccessPolicy;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Keys;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The default rules (deny by default):
 *
 * - "gcego-media.manage" (a Laravel Gate ability you define) may do everything;
 * - signed-in users may upload;
 * - view and download: public media, media they uploaded, or media attached
 *   to a record they may "view" (its Laravel policy);
 * - attach: the owner record's "update" ability (and the media's own view rule);
 * - replace and delete: media they uploaded (attached media also needs
 *   "update" on the owner record when one is given).
 *
 * A record without a policy grants nothing through its attachments.
 */
final readonly class DefaultMediaAccessPolicy implements MediaAccessPolicy
{
    public const string ManageAbility = 'gcego-media.manage';

    public function __construct(private Gate $gate) {}

    public function allows(?Authenticatable $user, MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): bool
    {
        if ($user === null) {
            return ($ability === MediaAbility::View || $ability === MediaAbility::Download) && $media?->visibility === MediaVisibility::Public;
        }

        $gate = $this->gate->forUser($user);

        if ($gate->has(self::ManageAbility) && $gate->allows(self::ManageAbility)) {
            return true;
        }

        $mine = $media !== null && $user instanceof Model && $media->isUploadedBy($user);

        return match ($ability) {
            MediaAbility::Upload => true,
            MediaAbility::Manage => false,
            MediaAbility::View, MediaAbility::Download => $media !== null && ($media->visibility === MediaVisibility::Public || $mine || $this->viewsAnOwner($user, $media, $owner)),
            MediaAbility::Attach => $owner !== null && $this->may($user, 'update', $owner) && ($media === null || $mine || $media->visibility === MediaVisibility::Public || $this->viewsAnOwner($user, $media, null)),
            MediaAbility::Replace, MediaAbility::Delete => $media !== null && $mine && ($owner === null || $this->may($user, 'update', $owner)),
        };
    }

    private function viewsAnOwner(Authenticatable $user, MediaRecord $media, ?Model $owner): bool
    {
        if ($owner !== null) {
            return $this->may($user, 'view', $owner) && $media->attachments()->where('attachable_type', $owner->getMorphClass())->where('attachable_id', Keys::string($owner->getKey()))->exists();
        }

        foreach ($media->attachments()->with('attachable')->limit(20)->get() as $attachment) {
            $attached = $attachment->attachable;

            if ($attached instanceof Model && $this->may($user, 'view', $attached)) {
                return true;
            }
        }

        return false;
    }

    private function may(Authenticatable $user, string $ability, Model $model): bool
    {
        $gate = $this->gate->forUser($user);

        return $gate->getPolicyFor($model) !== null && $gate->allows($ability, $model);
    }
}
