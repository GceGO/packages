<?php

declare(strict_types=1);

use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\SettingsPage;

// The same general group, edited for the request's institution.
return new SettingsPage(name: 'branding', title: 'Institution branding', group: 'general', scope: SettingScope::Tenant, sort: 5);
