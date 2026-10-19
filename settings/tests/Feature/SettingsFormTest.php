<?php

declare(strict_types=1);

use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Settings\History\SettingChange;
use Gcego\Settings\Livewire\SettingsForm;
use Gcego\Settings\Livewire\SettingsPanel;
use Gcego\Settings\Persistence\SettingRecord;
use Livewire\Livewire;

it('saves only the changed values, at the page scope, with the loaded versions', function (): void {
    $world = world();
    actAs($world['admin']);

    Livewire::actingAs($world['admin'])->test(SettingsForm::class, ['page' => 'general'])
        ->assertSet('data.site_name', 'GCEGO')
        ->set('data.site_name', 'GCEGO Learning Platform')
        ->set('data.max_upload_mb', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Your settings were saved.')
        ->assertSet('versions.site_name', 1)
        ->assertDispatched('gcego-settings-saved');

    expect(SettingRecord::query()->pluck('key')->all())->toBe(['site_name'])
        ->and(settings()->global()->get('site_name'))->toBe('GCEGO Learning Platform');
});

it('shows validation errors next to the fields and saves nothing', function (): void {
    $world = world();
    actAs($world['admin']);

    Livewire::actingAs($world['admin'])->test(SettingsForm::class, ['page' => 'general'])
        ->set('data.mail_enabled', true)
        ->set('data.site_name', 'Darn')
        ->call('save')
        ->assertHasErrors(['data.mail_from_address', 'data.site_name'])
        ->assertSee('The Sender address is required while Send mail is set this way.')
        ->assertSee('The Website name must be polite.')
        ->assertSeeHtml('aria-invalid="true"');

    expect(SettingRecord::query()->count())->toBe(0);
});

it('refuses a save over a newer value and keeps that value', function (): void {
    $world = world();
    actAs($world['admin']);
    $form = Livewire::actingAs($world['admin'])->test(SettingsForm::class, ['page' => 'general']);

    settings()->global()->asSystem()->set('site_name', 'Changed elsewhere');

    $form->set('data.site_name', 'Mine')->call('save')
        ->assertHasErrors('settings')
        ->assertSee('These settings changed while you were editing them.');

    expect(settings()->global()->get('site_name'))->toBe('Changed elsewhere');
});

it('replaces a secret only when a new one is typed, and never shows it', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('sms_api_key', 'old-key');
    actAs($world['principal'], 1);

    $form = Livewire::actingAs($world['principal'])->test(SettingsForm::class, ['page' => 'school'])
        ->assertSet('data.sms_api_key', null)
        ->assertSet('secrets.sms_api_key', true)
        ->set('data.school_name', 'North College')
        ->call('save')
        ->assertHasNoErrors();

    expect(settings()->forTenant(1)->get('sms_api_key'))->toBe('old-key');

    $form->set('data.sms_api_key', 'new-key')->call('save')->assertHasNoErrors()->assertSet('data.sms_api_key', null);

    expect(settings()->forTenant(1)->get('sms_api_key'))->toBe('new-key')
        ->and(SettingChange::query()->where('key', 'sms_api_key')->latest('id')->value('change'))->toBe('rotated')
        ->and(json_encode($form->snapshot))->not->toContain('new-key')->not->toContain('old-key');
});

it('resets an override to the inherited value', function (): void {
    $world = world();
    settings()->global()->asSystem()->set('site_name', 'Platform');
    settings()->forTenant(1)->asSystem()->set('site_name', 'North');
    actAs($world['principal'], 1);

    Livewire::actingAs($world['principal'])->test(SettingsForm::class, ['page' => 'branding'])
        ->assertSee('Set for This institution')
        ->call('resetSetting', 'site_name')
        ->assertHasNoErrors()
        ->assertSee('"Website name" now uses the inherited value.')
        ->assertSet('data.site_name', 'Platform')
        ->assertSee('Inherited from Platform');

    expect(SettingRecord::query()->where('key', 'site_name')->where('scope', 'tenant')->exists())->toBeFalse();
});

it('refuses resets of settings that are not on the page', function (): void {
    $world = world();
    settings()->global()->asSystem()->set('session_lifetime', 30);
    actAs($world['principal'], 1);

    Livewire::actingAs($world['principal'])->test(SettingsForm::class, ['page' => 'branding'])
        ->call('resetSetting', 'session_lifetime')
        ->assertHasErrors('settings');

    expect(settings()->global()->get('session_lifetime'))->toBe(30);
});

it('refuses state for settings that are not on the page', function (): void {
    $world = world();
    actAs($world['principal'], 1);

    Livewire::actingAs($world['principal'])->test(SettingsForm::class, ['page' => 'branding'])
        ->set('data.session_lifetime', 5);
})->throws(InvalidStateException::class);

it('saves a user\'s own preferences', function (): void {
    $world = world();
    actAs($world['teacher'], 1);

    Livewire::actingAs($world['teacher'])->test(SettingsForm::class, ['page' => 'preferences'])
        ->assertSet('data.preferred_theme', 'system')
        ->set('data.preferred_theme', 'dark')
        ->set('data.page_size', '50')
        ->call('save')
        ->assertHasNoErrors();

    actAs($world['principal'], 1);
    expect(settings()->forUser($world['teacher'])->get('preferred_theme')->value)->toBe('dark')
        ->and(settings()->forUser($world['teacher'])->get('page_size'))->toBe(50)
        ->and(settings()->get('preferred_theme')->value)->toBe('system');
});

it('searches only the settings the viewer may see, never values', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('sms_api_key', 'brand-secret');
    actAs($world['principal'], 1);

    Livewire::actingAs($world['principal'])->test(SettingsPanel::class)
        ->set('search', 'brand')
        ->assertSee('Website name')
        ->assertSee('Institution branding')
        ->assertDontSee('brand-secret')
        ->set('search', 'session')
        ->assertSee('No settings match your search.');
});

it('shows the history of the page scope, with secrets redacted', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->setMany(['school_name' => 'North College', 'sms_api_key' => 'hidden-key']);
    settings()->forTenant(2)->asSystem()->set('school_name', 'South College');
    actAs($world['principal'], 1);

    Livewire::actingAs($world['principal'])->test(SettingsPanel::class, ['page' => 'school'])
        ->set('tab', 'history')
        ->assertSee('"North College"')
        ->assertSee('Value hidden (sensitive setting).')
        ->assertSee('System (system)')
        ->assertDontSee('South College')
        ->assertDontSee('hidden-key');
});
