<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsPage;
use Gcego\Settings\Types\SettingType;

it('accepts the definitions shown in the README', function (): void {
    $definitions = [
        new Setting('site_name', 'GCEGO'),
        new Setting(name: 'maintenance_mode', default: false, type: SettingType::Boolean),
        new SettingsGroup('general', new Setting('site_name', 'GCEGO'), new Setting('site_description', ''), new Setting('support_email', '')),
        new Setting(new Property(Setting::Name, 'site_name'), new Property(Setting::Label, 'Website Name'), new Property(Setting::Default, 'GCEGO')),
        new Setting(name: 'site_name', label: 'Website Name', default: 'GCEGO'),
        new Setting(name: 'external_api_secret', default: null, encrypted: true),
        new SettingsPage(name: 'general', title: 'General Settings', group: 'general'),
        new SettingsPage(name: 'school', title: 'School settings', group: 'institution', scope: SettingScope::Tenant),
    ];

    foreach ($definitions as $definition) {
        expect($definition->validate()->isValid())->toBeTrue();
    }
});

it('runs the README usage examples', function (): void {
    $world = world();
    actAs($world['admin'], 1);
    $settings = settings();

    $settings->set(key: 'site_name', value: 'GCEGO Learning Platform');
    $settings->forTenant(1)->set('school_name', 'North College');
    $settings->forUser($world['admin'])->set('preferred_theme', 'dark');

    actAs($world['admin'], 1);
    expect($settings->get('site_name'))->toBe('GCEGO Learning Platform')
        ->and(settings()->forTenant(1)->get('school_name'))->toBe('North College')
        ->and(settings()->forUser($world['admin'])->get('preferred_theme')->value)->toBe('dark');
});
