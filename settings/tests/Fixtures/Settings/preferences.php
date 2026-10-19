<?php

declare(strict_types=1);

use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\Tests\Fixtures\Enums\Theme;

// Personal preferences: each person's own, inheriting institution and platform defaults.
return new SettingsGroup(
    'preferences',
    'Preferences',
    new Setting(name: 'preferred_theme', label: 'Theme', default: Theme::System, enum: Theme::class, scopes: ['global', 'tenant', 'user']),
    new Setting(name: 'table_density', label: 'Table density', default: 'comfortable', options: ['comfortable' => 'Comfortable', 'compact' => 'Compact'], scopes: ['global', 'user']),
    new Setting(name: 'page_size', label: 'Rows per page', default: 25, options: [10 => '10', 25 => '25', 50 => '50'], scopes: ['global', 'user']),
    scopes: ['global', 'user'],
);
