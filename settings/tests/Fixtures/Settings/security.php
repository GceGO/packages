<?php

declare(strict_types=1);

use Gcego\Security\Requirements\Ability;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\Tests\Fixtures\Enums\Level;

// Platform-wide security policy: never overridable by an institution.
return new SettingsGroup(
    'security',
    'Security',
    new Ability('security.manage'),
    new Setting(name: 'session_lifetime', label: 'Session lifetime (minutes)', default: 120, rules: ['min:5', 'max:1440']),
    new Setting(name: 'password_strength', label: 'Password strength', default: Level::High, enum: Level::class),
    new Setting(name: 'ratio', label: 'Ratio', default: 0.5),
    new Setting(name: 'allowed_origins', label: 'Allowed origins', default: ['https://gcego.test']),
    new Setting(name: 'feature_flags', label: 'Feature flags', default: null, type: 'json'),
    new Setting(name: 'audit_until', label: 'Audit until', default: null, type: 'datetime'),
    sort: 10,
);
