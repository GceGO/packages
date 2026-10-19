<?php

declare(strict_types=1);

use Gcego\Modules\Module;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\SecurityManager;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Installation\InstallationManager;
use Gcego\Studio\Installation\InstallationResult;
use Gcego\Studio\Tests\Fixtures\Extensions\ReportsExtension;
use Gcego\Studio\Tests\Fixtures\Extensions\ReportsInstallationStep;
use Gcego\Studio\Tests\Fixtures\Models\Subject;
use Gcego\Studio\Tests\Fixtures\Models\User;
use Gcego\Studio\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

/**
 * Points the application (and its discovery folders) at an empty workspace.
 */
function freshApplication(): string
{
    $base = TestCase::workspace('app-root');
    @mkdir($base.'/config', 0777, true);
    app()->setBasePath($base);
    config([
        'gcego-panels.discovery.paths' => [$base.'/app/GCEGo/Panels'],
        'gcego-modules.discovery.paths' => [$base.'/app/GCEGo/Modules'],
        'gcego-registry.discovery.roots' => [['path' => $base.'/app/GCEGo/Pages', 'kind' => 'page', 'entrypoint' => 'file']],
    ]);

    return $base;
}

it('installs configuration, a starter panel and its dashboard', function (): void {
    $base = freshApplication();

    $this->artisan('gcego:install', ['--no-interaction' => true])
        ->expectsOutputToContain('GCEGO Admin Studio is installed.')
        ->expectsOutputToContain("Gate::define('access-admin'")
        ->assertSuccessful();

    expect(file_get_contents($base.'/config/gcego-studio.php'))->toBe(file_get_contents(dirname(__DIR__, 2).'/config/gcego-studio.php'))
        ->and(is_file($base.'/app/GCEGo/Panels/admin.php'))->toBeTrue()
        ->and(is_file($base.'/app/GCEGo/Pages/dashboard.php'))->toBeTrue();

    foreach (['app/GCEGo/Panels/admin.php', 'app/GCEGo/Pages/dashboard.php'] as $file) {
        exec('php -l '.escapeshellarg($base.'/'.$file), $output, $code);
        expect($code)->toBe(0);
    }

    $panel = require $base.'/app/GCEGo/Panels/admin.php';
    expect($panel)->toBeInstanceOf(Panel::class)->and($panel->validate()->errors())->toBe([])
        ->and(array_map(static fn ($requirement): string => $requirement::class, $panel->requirements()))->toContain(Ability::class)
        ->and(file_get_contents($base.'/app/GCEGo/Panels/admin.php'))->toContain("new Ability('access-admin')")
        ->and((require $base.'/app/GCEGo/Pages/dashboard.php')->validate()->errors())->toBe([]);
});

it('is idempotent and keeps files you changed', function (): void {
    $base = freshApplication();
    $installer = app(InstallationManager::class);
    $installer->install($installer->plan());

    $again = $installer->install($installer->plan());
    expect($again->status)->toBe(InstallationResult::Installed)->and($again->created)->toBe([])->and($again->unchanged)->toHaveCount(3);

    file_put_contents($base.'/app/GCEGo/Panels/admin.php', "<?php\n// mine\n");
    $kept = $installer->install($installer->plan());

    expect($kept->status)->toBe(InstallationResult::Partial)->and($kept->kept)->toBe(['app/GCEGo/Panels/admin.php'])
        ->and(file_get_contents($base.'/app/GCEGo/Panels/admin.php'))->toBe("<?php\n// mine\n");

    $this->artisan('gcego:install', ['--no-interaction' => true])->expectsOutputToContain('Kept your app/GCEGo/Panels/admin.php')->assertSuccessful();
});

it('overwrites only what differs, and only with --force or a confirmation', function (): void {
    $base = freshApplication();
    app(InstallationManager::class)->install(app(InstallationManager::class)->plan());
    file_put_contents($base.'/app/GCEGo/Panels/admin.php', "<?php\n// mine\n");

    $this->artisan('gcego:install')->expectsConfirmation('app/GCEGo/Panels/admin.php already exists and differs. Overwrite it?', 'no')->assertSuccessful();
    expect(file_get_contents($base.'/app/GCEGo/Panels/admin.php'))->toBe("<?php\n// mine\n");

    $this->artisan('gcego:install', ['--force' => true, '--no-interaction' => true])->expectsOutputToContain('overwrite (--force)')->assertSuccessful();
    expect(file_get_contents($base.'/app/GCEGo/Panels/admin.php'))->toContain('new Panel(');
});

it('plans without writing on --dry-run', function (): void {
    $base = freshApplication();

    $this->artisan('gcego:install', ['--dry-run' => true, '--panel' => 'staff'])->expectsOutputToContain('Dry run: nothing was written.')->assertSuccessful();

    expect(is_dir($base.'/app'))->toBeFalse()->and(is_file($base.'/config/gcego-studio.php'))->toBeFalse();
});

it('creates a read-only example module for an existing model, referenced by the panel', function (): void {
    $base = freshApplication();

    $this->artisan('gcego:install', ['--model' => Subject::class, '--module' => 'Academics', '--no-interaction' => true])->assertSuccessful();

    $module = require $base.'/app/GCEGo/Modules/Academics/Module.php';
    expect($module)->toBeInstanceOf(Module::class)->and($module->validate()->errors())->toBe([])
        ->and(file_get_contents($base.'/app/GCEGo/Modules/Academics/Module.php'))->not->toContain('new Create')->not->toContain('new Edit')
        ->and(file_get_contents($base.'/app/GCEGo/Panels/admin.php'))->toContain("new ModuleReference('academics')");
});

it('refuses invalid options and missing models without writing anything', function (): void {
    $base = freshApplication();

    $this->artisan('gcego:install', ['--panel' => '../etc', '--no-interaction' => true])->expectsOutputToContain('The panel name must be')->assertFailed();
    $this->artisan('gcego:install', ['--model' => 'App\\Models\\Nope', '--no-interaction' => true])->expectsOutputToContain('--model must name an existing Eloquent model')->assertFailed();
    $this->artisan('gcego:install', ['--model' => Subject::class, '--module' => 'bad name', '--no-interaction' => true])->assertFailed();

    expect(is_dir($base.'/app'))->toBeFalse();
});

it('adds extension installation steps, but never writes outside the application or through symlinks', function (): void {
    $this->reboot(['gcego-studio.extensions' => [ReportsExtension::class]]);
    $base = freshApplication();
    ReportsInstallationStep::$path = $base.'/config/acme-reports.php';

    $plan = app(InstallationManager::class)->plan();
    expect(collect($plan->files)->pluck('description')->all())->toContain('Reports configuration');
    app(InstallationManager::class)->install($plan);
    expect(is_file($base.'/config/acme-reports.php'))->toBeTrue();

    ReportsInstallationStep::$path = $base.'/../outside.php';
    expect(app(InstallationManager::class)->plan()->problems)->toContain('Refusing to write outside the application: '.$base.'/../outside.php.');

    @unlink($base.'/config/acme-reports.php');
    symlink(TestCase::workspace('target.php'), $base.'/config/acme-reports.php');
    ReportsInstallationStep::$path = $base.'/config/acme-reports.php';
    $result = app(InstallationManager::class)->install(app(InstallationManager::class)->plan());

    expect($result->errors)->toHaveCount(1)->and($result->errors[0])->toContain('symbolic link')->and(is_file(TestCase::workspace('target.php')))->toBeFalse();
    ReportsInstallationStep::$path = null;
});

it('creates no users, roles or permissions, and the starter panel admits nobody until you allow it', function (): void {
    $base = freshApplication();
    $users = User::query()->count();
    $this->artisan('gcego:install', ['--panel' => 'staff', '--no-interaction' => true])->assertSuccessful();

    expect(User::query()->count())->toBe($users)->and(Gate::has('access-staff'))->toBeFalse();

    $world = world();
    app(RegistryInterface::class)->register(require $base.'/app/GCEGo/Panels/staff.php');
    $panel = app(PanelManager::class)->panel('staff');
    $allowed = app(SecurityManager::class)->actingAs($world['admin'], null, static fn (): bool => ! app(SecurityManager::class)->check(...$panel->requirements())->denied());

    // "access-admin" is the configured ability; it is defined for Admin in the test application, so define your own before going live.
    expect(file_get_contents($base.'/app/GCEGo/Panels/staff.php'))->toContain("new Ability('access-admin')")->and($allowed)->toBeTrue();

    config(['gcego-studio.install.ability' => 'access-staff']);
    app()->forgetInstance(StudioConfiguration::class);
    $this->artisan('gcego:install', ['--panel' => 'ops', '--no-interaction' => true])->assertSuccessful();
    app(RegistryInterface::class)->register(require $base.'/app/GCEGo/Panels/ops.php');
    $ops = app(PanelManager::class)->panel('ops');
    $denied = app(SecurityManager::class)->actingAs($world['admin'], null, static fn (): bool => app(SecurityManager::class)->check(...$ops->requirements())->denied());

    expect($denied)->toBeTrue();
});
