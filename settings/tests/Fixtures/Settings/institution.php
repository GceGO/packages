<?php

declare(strict_types=1);

use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;

// Each institution's own settings (the tenant scope only).
return new SettingsGroup(
    'institution',
    'Institution',
    new Setting(name: 'school_name', label: 'School name', default: ''),
    new Setting(name: 'school_motto', label: 'Motto', default: '', approval: 'settings-approval'),
    new Setting(name: 'sms_api_key', label: 'SMS API key', default: null, sensitive: true),
    new Setting(name: 'school_logo', label: 'Logo', default: null, type: 'media'),
    scopes: ['tenant'],
);
