<?php

declare(strict_types=1);

use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\Tests\Fixtures\Rules\NoProfanity;
use Gcego\Settings\Types\SettingType;

// Platform settings; some may be overridden per institution or per panel.
return new SettingsGroup(
    'general',
    'General',
    new Setting(name: 'site_name', label: 'Website name', default: 'GCEGO', required: true, scopes: ['global', 'panel', 'tenant'], rules: ['max:60', NoProfanity::class], keywords: ['brand', 'title']),
    new Setting('site_description', ''),
    new Setting(name: 'maintenance_mode', default: false, type: SettingType::Boolean),
    new Setting(name: 'max_upload_mb', default: 10, rules: ['min:1', 'max:100'], scopes: ['global', 'tenant']),
    new Setting(name: 'launch_date', type: SettingType::Date, default: '2026-09-01'),
    new SettingsSection('mail', 'Outgoing mail',
        new Setting(name: 'mail_enabled', label: 'Send mail', default: false),
        new Setting(name: 'mail_from_address', label: 'Sender address', default: '', field: 'email', requiredWhen: ['mail_enabled' => true]),
        new Setting(name: 'newsletter_enabled', label: 'Newsletter', default: false, requires: ['mail_enabled']),
        columns: 2,
    ),
    new SettingsSection('integrations', 'Integrations',
        new Setting(name: 'external_api_secret', label: 'API secret', default: null, encrypted: true),
        new Setting(name: 'webhook_url', label: 'Webhook URL', default: null, field: 'url'),
    ),
);
