<?php

declare(strict_types=1);

namespace Gcego\Settings\Contracts;

use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Who is told that sensitive settings changed (configured as
 * gcego-settings.notifications.recipients). Return only people allowed to
 * know about that scope instance (for a tenant, its own administrators):
 * the notification names the settings and the scope, never values.
 */
interface SettingsRecipients
{
    /**
     * @param  list<SettingDefinition>  $definitions  The sensitive settings that changed.
     * @return iterable<Model>
     */
    public function recipients(array $definitions, ScopeContext $context): iterable;
}
