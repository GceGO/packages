<?php

declare(strict_types=1);

namespace Gcego\Settings\Approvals;

use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The Laravel policy of SettingRequest, used by gcego/workflows:
 *
 * - update (starting the workflow, withdrawing): the requester, while pending;
 * - view: the requester, or anyone who may approve;
 * - approve (the ability approval transitions name): someone other than
 *   the requester (separation of duties) who may change that setting at
 *   that scope themselves.
 */
final readonly class SettingRequestPolicy
{
    public function __construct(private SettingApprovals $approvals, private SettingsDefinitions $definitions, private SettingsAuthorizer $authorizer) {}

    public function update(Authenticatable $user, SettingRequest $request): bool
    {
        return $request->isPending() && $user instanceof Model && $request->requestedBy($user);
    }

    public function view(Authenticatable $user, SettingRequest $request): bool
    {
        return ($user instanceof Model && $request->requestedBy($user)) || $this->approve($user, $request);
    }

    public function approve(Authenticatable $user, SettingRequest $request): bool
    {
        if (! $request->isPending() || ($user instanceof Model && $request->requestedBy($user))) {
            return false;
        }

        try {
            return $this->authorizer->canUpdate($user, $this->definitions->definition($request->key), $this->approvals->context($request));
        } catch (Throwable) {
            return false;
        }
    }
}
