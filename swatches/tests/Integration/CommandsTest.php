<?php

declare(strict_types=1);

use Gcego\Swatches\Facades\Swatches;
use Gcego\Swatches\SwatchesManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

it('lists palettes and tokens', function (): void {
    $this->artisan('gcego:swatches:list')->expectsOutputToContain('emerald')->assertSuccessful();
    $this->artisan('gcego:swatches:list tokens --theme=ocean')->expectsOutputToContain('sidebar-active-foreground')->assertSuccessful();
    $this->artisan('gcego:swatches:list colors')->assertExitCode(2);

    Artisan::call('gcego:swatches:list', ['type' => 'tokens', '--format' => 'json']);
    $json = json_decode(Artisan::output(), true);

    expect($json['theme'])->toBe('gcego')
        ->and(collect($json['tokens'])->firstWhere('name', 'ring'))->toMatchArray(['light' => '{primary}', 'resolved_light' => '#0b7570', 'resolved_dark' => '#3ec2b5']);
});

it('lists themes with their status', function (): void {
    Artisan::call('gcego:swatches:themes', ['--format' => 'json']);
    $json = json_decode(Artisan::output(), true);

    expect(array_column($json['themes'], 'name'))->toBe(['gcego', 'midnight', 'slate', 'ocean', 'forest'])
        ->and(array_unique(array_column($json['themes'], 'valid')))->toBe([true]);

    $this->artisan('gcego:swatches:themes')->expectsOutputToContain('GCEGO Midnight')->assertSuccessful();
});

it('validates themes and fails on errors', function (): void {
    $this->artisan('gcego:swatches:validate')->expectsOutputToContain('0 error(s)')->assertSuccessful();

    $this->rebuildApplication(['gcego-swatches.themes' => ['faint' => ['light' => ['muted-foreground' => '#b0b0b0']]]]);

    $this->artisan('gcego:swatches:validate faint')->expectsOutputToContain('Set muted-foreground to')->assertFailed();
    $this->artisan('gcego:swatches:validate nope')->assertFailed();
});

it('checks contrast between colors and tokens', function (): void {
    Artisan::call('gcego:swatches:contrast', ['foreground' => '#111827', 'background' => 'white', '--format' => 'json']);
    $json = json_decode(Artisan::output(), true);

    // 17.737…: reports truncate, so a ratio is never shown above its real value.
    expect($json['ratio'])->toBe(17.73)
        ->and($json['passes'])->toBe(['text' => true, 'large-text' => true, 'non-text' => true, 'aaa-text' => true]);

    $this->artisan('gcego:swatches:contrast primary-foreground primary --theme=ocean --dark')->expectsOutputToContain('pass')->assertSuccessful();
    $this->artisan('gcego:swatches:contrast "#777" white')->expectsOutputToContain('Nearest passing foreground')->assertSuccessful();
    $this->artisan('gcego:swatches:contrast bogus white')->assertExitCode(2);
});

it('generates a palette from a brand color', function (): void {
    Artisan::call('gcego:swatches:palette', ['color' => '#2563EB', '--format' => 'json']);
    $json = json_decode(Artisan::output(), true);

    expect($json['steps'][700])->toBe('#2563eb')
        ->and(count($json['steps']))->toBe(11);

    $this->artisan('gcego:swatches:palette', ['color' => 'url(x)'])->assertExitCode(2);
});

it('writes, checks and refuses to escape the project for the application stylesheet', function (): void {
    $this->rebuildApplication([
        'gcego-swatches.palettes' => ['brand' => '#2563eb'],
        'gcego-swatches.themes' => ['campus' => ['light' => ['primary' => '#1d4ed8']]],
    ]);

    $relative = 'storage/framework/testing/gcego-swatches-'.getmypid().'.css';
    $path = base_path($relative);
    @unlink($path);

    $this->artisan('gcego:swatches:generate', ['--output' => $relative, '--check' => true])->assertFailed();
    $this->artisan('gcego:swatches:generate', ['--output' => $relative])->assertSuccessful();
    $this->artisan('gcego:swatches:generate', ['--output' => $relative, '--check' => true])->assertSuccessful();

    expect((string) file_get_contents($path))->toContain('--color-brand-700: #2563eb;')
        ->toContain(':root[data-gcego-theme="campus"]:not(.dark)')
        ->not->toContain('data-gcego-theme="ocean"');

    $this->artisan('gcego:swatches:generate', ['--output' => '../outside.css'])->assertExitCode(2);
    @unlink($path);
});

it('confirms the package stylesheets are current', function (): void {
    $this->artisan('gcego:swatches:generate', ['--package' => true, '--check' => true])->expectsOutputToContain('up to date')->assertSuccessful();
});

it('caches compiled CSS, serves it, and ignores it once themes change', function (): void {
    $this->rebuildApplication(['gcego-swatches.themes' => ['campus' => ['light' => ['primary' => '#1d4ed8']]]]);
    $swatches = app(SwatchesManager::class);
    $path = $swatches->cache()->path;

    // The four presets and "campus".
    $this->artisan('gcego:swatches:cache')->expectsOutputToContain('5 runtime theme')->assertSuccessful();

    expect(is_file($path))->toBeTrue()
        ->and($swatches->cache()->read()['themes']['campus'] ?? null)->toContain('#1d4ed8');

    // A tampered entry is served only while the fingerprint matches …
    $data = require $path;
    $data['themes']['campus'] = ':root{--gcego-primary:#000000;}';
    file_put_contents($path, '<?php return '.var_export($data, true).';');

    expect(app(SwatchesManager::class)->themeCss('campus'))->toBe(':root{--gcego-primary:#000000;}');

    // … and a changed theme set invalidates it.
    $this->rebuildApplication(['gcego-swatches.themes' => ['campus' => ['light' => ['primary' => '#1e40af']]]]);

    expect(app(SwatchesManager::class)->themeCss('campus'))->toContain('#1e40af');

    $this->artisan('gcego:swatches:clear')->assertSuccessful();

    expect(is_file($path))->toBeFalse();
});

it('hooks into php artisan optimize and optimize:clear', function (): void {
    // Running "optimize" itself would cache routes into the shared Testbench skeleton.
    expect(ServiceProvider::$optimizeCommands['swatches'] ?? null)->toBe('gcego:swatches:cache')
        ->and(ServiceProvider::$optimizeClearCommands['swatches'] ?? null)->toBe('gcego:swatches:clear');
});

it('reports APCA and simulated color vision in the contrast command', function (): void {
    Artisan::call('gcego:swatches:contrast', ['foreground' => '#888888', 'background' => 'white', '--format' => 'json']);
    $json = json_decode(Artisan::output(), true);

    expect($json['apca'])->toBe(['lc' => 63.1, 'text' => true, 'large-text' => true, 'non-text' => true])
        ->and(array_keys($json['color_vision']))->toBe(['protanopia', 'deuteranopia', 'tritanopia', 'achromatopsia'])
        ->and($json['color_vision']['achromatopsia']['foreground'])->toBe('#888888');

    $this->artisan('gcego:swatches:contrast "#888" white')->expectsOutputToContain('Lc 63.1')->assertSuccessful();
});

it('runs advisory checks from the validate command without failing', function (): void {
    $this->artisan('gcego:swatches:validate gcego --apca --color-vision')
        ->expectsOutputToContain('below APCA')
        ->expectsOutputToContain('look alike')
        ->assertSuccessful();

    $this->artisan('gcego:swatches:validate gcego --apca --strict')->assertFailed();
});

it('suggests a brand from a logo', function (): void {
    $path = sys_get_temp_dir().'/gcego-command-logo-'.getmypid().'.svg';
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><path fill="#facc15" d="M0 0"/><path fill="#facc15" d="M1 1"/><path fill="#1d4ed8" d="M2 2"/></svg>');

    Artisan::call('gcego:swatches:logo', ['path' => $path, '--format' => 'json']);
    $json = json_decode(Artisan::output(), true);

    expect($json['source'])->toBe('svg')
        ->and($json['suggestion'])->toBe(['primary' => '#facc15', 'accent' => '#1d4ed8', 'theme' => null])
        ->and($json['compiled']['light']['primary'])->not->toBe('#facc15')
        ->and($json['compiled']['notes'][0])->toContain('darkened');

    $this->artisan('gcego:swatches:logo', ['path' => $path])->expectsOutputToContain('Suggested primary')->assertSuccessful();
    $this->artisan('gcego:swatches:logo', ['path' => '/nope/logo.png'])->expectsOutputToContain('readable local file')->assertFailed();
});

it('extracts logo colors through the facade', function (): void {
    $path = sys_get_temp_dir().'/gcego-facade-logo-'.getmypid().'.svg';
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><path fill="#047857" d="M0 0"/></svg>');

    expect(Swatches::brandFromLogo($path, 'ocean')->toArray())->toBe(['primary' => '#047857', 'accent' => null, 'theme' => 'ocean']);
});
