<?php

declare(strict_types=1);

namespace Gcego\Settings\Contracts;

use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\SettingsPage;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see and change settings, deny by default. Bound to
 * Gcego\Settings\Security\SettingAccessPolicy; an application may bind
 * its own implementation, which must also deny when unsure.
 */
interface SettingsAuthorizer
{
    /** See the setting's value at the context's target (sensitive values: only whether one is set). */
    public function canView(?Authenticatable $user, SettingDefinition $definition, ScopeContext $context): bool;

    /** Store, replace, rotate or reset the setting at the context's target. */
    public function canUpdate(?Authenticatable $user, SettingDefinition $definition, ScopeContext $context): bool;

    /** Open a settings page (its own requirements; settings are checked one by one). */
    public function canOpen(?Authenticatable $user, SettingsPage $page, ScopeContext $context): bool;

    public function canExport(?Authenticatable $user, ScopeContext $context): bool;

    public function canImport(?Authenticatable $user, ScopeContext $context): bool;
}
