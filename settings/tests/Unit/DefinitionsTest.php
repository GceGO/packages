<?php

declare(strict_types=1);

use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Properties\Property;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsPage;
use Gcego\Settings\Tests\Fixtures\Enums\Theme;
use Gcego\Settings\Types\SettingType;

it('normalizes minimal, explicit and property definitions into the same definition', function (): void {
    $minimal = new Setting('site_name', 'GCEGO');
    $explicit = new Setting(name: 'site_name', default: 'GCEGO');
    $properties = new Setting(new Property(Setting::Name, 'site_name'), new Property(Setting::Default, 'GCEGO'));

    expect($explicit->normalize())->toBe($minimal->normalize())
        ->and($properties->normalize())->toBe($minimal->normalize())
        ->and($minimal->key())->toBe('site_name')
        ->and($minimal->settingType())->toBe(SettingType::String)
        ->and($minimal->isNullable())->toBeFalse();
});

it('infers types from defaults and treats missing and null defaults as nullable', function (): void {
    expect((new Setting('a', false))->settingType())->toBe(SettingType::Boolean)
        ->and((new Setting('b', 3))->settingType())->toBe(SettingType::Integer)
        ->and((new Setting('c', 1.5))->settingType())->toBe(SettingType::Float)
        ->and((new Setting('d', ['x']))->settingType())->toBe(SettingType::Array)
        ->and((new Setting(new Property(Setting::Name, 'e'), new Property(Setting::Default, Theme::Dark)))->settingType())->toBe(SettingType::Enum)
        ->and((new Setting(name: 'f', enum: Theme::class))->settingType())->toBe(SettingType::Enum)
        ->and((new Setting('g'))->isNullable())->toBeTrue()
        ->and((new Setting('h', null))->isNullable())->toBeTrue()
        ->and((new Setting('h', null))->hasDefault())->toBeTrue()
        ->and((new Setting('g'))->hasDefault())->toBeFalse()
        ->and((new Setting(name: 'i', default: null, encrypted: true))->isSensitive())->toBeTrue();
});

it('refuses unknown named arguments', function (): void {
    expect(fn () => new Setting(name: 'site_name', colour: 'red'))->toThrow(InvalidSettingException::class, 'it has no "colour" argument');
});

it('reports invalid settings with actionable messages', function (Setting $setting, string $message): void {
    $group = new SettingsGroup('broken', $setting);

    expect(fn () => $group->assertValid())->toThrow(InvalidComponentException::class, $message);
})->with([
    'bad key' => [new Setting('Site Name', 'x'), 'a lowercase key'],
    'unknown type' => [new Setting(name: 'a', type: 'money'), 'one of'],
    'default of another type' => [new Setting(name: 'a', default: 'many', type: SettingType::Integer), 'has a default that is not a valid integer value (integer)'],
    'null default, not nullable' => [new Setting(name: 'a', default: null, nullable: false), 'has a null default but is not nullable'],
    'enum without class' => [new Setting(name: 'a', type: SettingType::Enum), 'without a backed enum class'],
    'options on a boolean' => [new Setting(name: 'a', default: false, options: ['1' => 'Yes']), 'declares Options'],
    'default outside options' => [new Setting(name: 'a', default: 'red', options: ['blue' => 'Blue']), 'not one of its Options'],
    'field for another type' => [new Setting(name: 'a', default: 3, field: 'toggle'), 'cannot edit integer values'],
    'unknown scope' => [new Setting(name: 'a', scopes: ['planet']), 'scopes among global'],
    'precedence of other scopes' => [new Setting(name: 'a', scopes: ['global', 'tenant'], precedence: ['user', 'global']), 'Precedence that is not exactly its Scopes'],
    'self dependency' => [new Setting(name: 'a', default: false, requires: ['a']), 'depends on itself'],
    'pipe in a rule' => [new Setting(name: 'a', rules: ['min:1|max:3']), 'rule strings without "|"'],
]);

it('refuses duplicate keys and sections inside a group', function (): void {
    expect(fn () => (new SettingsGroup('dup', new Setting('a', ''), new SettingsSection('s', 'S', new Setting('a', ''))))->assertValid())->toThrow(InvalidComponentException::class, 'the setting "a" is declared 2 times')
        ->and(fn () => (new SettingsGroup('dup', new SettingsSection('s', 'S'), new SettingsSection('s', 'T')))->assertValid())->toThrow(InvalidComponentException::class, 'the section "s" is declared 2 times');
});

it('refuses keys declared in two groups, unknown dependencies, cycles and pages of unknown groups', function (): void {
    settings()->register(new SettingsGroup('other', new Setting('site_name', 'Copy')));
    settings()->register(new SettingsGroup('loops', new Setting(name: 'loop_a', default: false, requires: ['loop_b']), new Setting(name: 'loop_b', default: false, requires: ['loop_a']), new Setting(name: 'lost', default: '', requiredWhen: ['ghost' => true])));
    settings()->register(new SettingsPage(name: 'nowhere', group: 'missing'));

    $problems = implode("\n", settings()->definitions()->problems());

    expect($problems)->toContain('The setting "site_name" is declared in several groups (general, other)')
        ->toContain('"lost" depends on the unknown setting "ghost"')
        ->toContain('"loop_a" -> "loop_b" -> "loop_a"')
        ->toContain('The settings page "nowhere" shows the unknown group "missing"')
        ->and(fn () => settings()->get('maintenance_mode'))->toThrow(InvalidSettingException::class);
});

it('derives scopes, precedence, requirements and a default page from the group', function (): void {
    $definitions = settings()->definitions();
    $theme = $definitions->definition('preferred_theme');
    $session = $definitions->definition('session_lifetime');

    expect($theme->scopes)->toBe([SettingScope::Global, SettingScope::Tenant, SettingScope::User])
        ->and($theme->precedence)->toBe([SettingScope::User, SettingScope::Tenant, SettingScope::Global])
        ->and($definitions->definition('table_density')->scopes)->toBe([SettingScope::Global, SettingScope::User])
        ->and($session->scopes)->toBe([SettingScope::Global])
        ->and($session->requirements)->toHaveCount(1)
        ->and($definitions->page('security')->groupName())->toBe('security')
        ->and($definitions->page('security')->scope())->toBe(SettingScope::Global)
        ->and($definitions->hasPage('institution'))->toBeFalse();
});

it('drops scopes the configuration disables', function (): void {
    $this->reboot(['gcego-settings.scopes.tenant' => false]);

    expect(settings()->definition('site_name')->scopes)->toBe([SettingScope::Global, SettingScope::Panel])
        ->and(settings()->definitions()->hasPage('school'))->toBeFalse();
});

it('refuses configuration that would weaken security', function (string $key): void {
    expect(fn () => $this->reboot([$key => false]))->toThrow(InvalidSettingException::class, 'must be true');
})->with(['gcego-settings.security.require_authorization', 'gcego-settings.security.encrypt_sensitive_values', 'gcego-settings.security.enforce_tenant_scope']);
