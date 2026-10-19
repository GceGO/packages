<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ValidatedInput;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\SecurityManager;
use Gcego\Settings\Contracts\SettingsRecipients;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Integration\Actions\ResetSettings;
use Gcego\Settings\Integration\Actions\SaveSettings;
use Gcego\Settings\Persistence\SettingRecord;
use Gcego\Settings\Registry\SettingsKind;
use Gcego\Settings\Registry\SettingsPageKind;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsPage;
use Gcego\Settings\SettingsServiceProvider;
use Gcego\Settings\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

it('discovers groups and pages through gcego/registry', function (): void {
    $registry = app(RegistryInterface::class);

    expect($registry->names(SettingsKind::Id))->toEqualCanonicalizing(['general', 'institution', 'preferences', 'security'])
        ->and($registry->names(SettingsPageKind::Id))->toEqualCanonicalizing(['branding', 'general', 'preferences', 'school'])
        ->and($registry->get('settings:general'))->toBeInstanceOf(SettingsGroup::class)
        ->and($registry->get('settings-page:school'))->toBeInstanceOf(SettingsPage::class)
        ->and($registry->descriptor('settings:general')->metadata)->toMatchArray(['title' => 'General', 'settings' => 10, 'sections' => 2]);
});

it('describes itself as a GCEGO package', function (): void {
    $descriptor = (new SettingsServiceProvider(app()))->descriptor();

    expect($descriptor->id)->toBe('gcego/settings')
        ->and($descriptor->capabilities)->toContain('settings.scopes');
});

it('inspects definitions and stored values without showing values', function (): void {
    settings()->global()->asSystem()->set('external_api_secret', 'never-printed');
    SettingRecord::query()->insert(['key' => 'retired_flag', 'scope' => 'global', 'scope_key' => '', 'value' => 'true', 'type' => 'boolean', 'encrypted' => false, 'version' => 1]);

    $exit = Artisan::call('gcego:settings:inspect', ['--json' => true]);
    $output = Artisan::output();
    $report = json_decode($output, true);

    expect($exit)->toBe(0)
        ->and($output)->not->toContain('never-printed')
        ->and($report['runtime']['orphaned'])->toBe(['retired_flag @ global'])
        ->and($report['config']['encryption'])->toBeTrue()
        ->and(array_column($report['groups'], 'name'))->toBe(['general', 'institution', 'preferences', 'security']);
});

it('clears the cache from the console', function (): void {
    expect(Artisan::call('gcego:settings:clear-cache'))->toBe(0);
});

it('tells the configured recipients that sensitive settings changed, without values', function (): void {
    $world = world();
    $this->app->bind(Recipients::class, static fn (): Recipients => new Recipients(User::query()->where('name', 'Admin')->sole()));
    config(['gcego-settings.notifications.recipients' => Recipients::class]);
    $this->app->forgetInstance(SettingsConfig::class);
    app()->forgetScopedInstances();

    settings()->global()->asSystem()->setMany(['external_api_secret' => 'tell-no-one', 'site_name' => 'Public']);

    $notification = $world['admin']->notifications()->where('gcego_category', 'settings')->sole();

    expect(json_encode($notification->data))->toContain('API secret')->not->toContain('tell-no-one')->not->toContain('Website name');
});

it('saves and resets settings from gcego/actions handlers, as the acting user', function (): void {
    $world = world();
    $save = new class extends SaveSettings
    {
        protected function keys(): array
        {
            return ['site_name'];
        }
    };
    $reset = new class extends ResetSettings
    {
        protected function keys(): array
        {
            return ['site_name'];
        }
    };
    $context = static fn (array $input): ActionContext => new ActionContext(
        new ResolvedAction('settings', new Blueprint('settings'), new Action('save-settings'), 'settings', null),
        app(SecurityManager::class)->context(),
        input: new ValidatedInput($input),
    );

    actAs($world['admin']);
    $save->handle($context(['site_name' => 'From an action', 'maintenance_mode' => true]));

    expect(settings()->global()->get('site_name'))->toBe('From an action')
        ->and(settings()->global()->get('maintenance_mode'))->toBeFalse();

    actAs($world['principal']);
    expect(fn () => $save->handle($context(['site_name' => 'Not allowed'])))->toThrow(SettingAuthorizationException::class);

    actAs($world['admin']);
    expect(fn () => $save->handle($context(['site_name' => ''])))->toThrow(ValidationException::class);

    $reset->handle($context([]));
    expect(settings()->global()->get('site_name'))->toBe('GCEGO');
});

final class Recipients implements SettingsRecipients
{
    public function __construct(private readonly User $user) {}

    public function recipients(array $definitions, ScopeContext $context): iterable
    {
        expect(array_map(static fn (SettingDefinition $definition): string => $definition->key, $definitions))->toBe(['external_api_secret']);

        return [$this->user];
    }
}
