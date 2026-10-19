<?php

declare(strict_types=1);

namespace Gcego\Media\Contracts;

use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Models\MediaRecord;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may do what with media (gcego-media.security.access_policy).
 *
 * Called only after gcego/media's own checks passed: the record is in the
 * viewer's tenant and servable. Deny by default: return true only for
 * what you mean to allow. $owner is the record the media is used with,
 * when known (attaching to it, or viewing it on its page).
 */
interface MediaAccessPolicy
{
    public function allows(?Authenticatable $user, MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): bool;
}
