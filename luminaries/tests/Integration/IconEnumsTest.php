<?php

declare(strict_types=1);

use Gcego\Luminaries\Contracts\IconReference;
use Gcego\Luminaries\Exceptions\InvalidIconNameException;
use Gcego\Luminaries\Facades\Luminaries;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Icons\Alias;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Luminaries\Icons\HeroiconMicro;
use Gcego\Luminaries\Icons\HeroiconMini;
use Gcego\Luminaries\Icons\HeroiconSolid;
use Gcego\Luminaries\Icons\Lucide;
use Gcego\Luminaries\Support\IconEnumGenerator;
use Gcego\Luminaries\Tests\Fixtures\Enums\Priority;
use Gcego\Luminaries\Tests\Fixtures\Enums\SchoolIcon;
use Illuminate\Support\Facades\Blade;

it('keeps the generated enums in step with the installed icon sets', function (): void {
    $this->artisan('gcego:icons:enums', ['--check' => true])
        ->expectsOutputToContain('Icon enums are up to date.')
        ->assertSuccessful();
});

it('has one case per installed icon, each resolving to itself', function (string $enum, string $provider): void {
    $values = array_map(static fn (IconReference $icon): string => $icon->value, $enum::cases());

    expect($values)->toHaveCount(count(Luminaries::icons($provider)))
        ->and(array_filter($enum::cases(), static fn (IconReference $icon): bool => Luminaries::resolve($icon)->canonical() !== $icon->value))->toBe([]);
})->with([
    'Heroicon' => [Heroicon::class, 'heroicon'],
    'HeroiconSolid' => [HeroiconSolid::class, 'heroicon-solid'],
    'HeroiconMini' => [HeroiconMini::class, 'heroicon-mini'],
    'HeroiconMicro' => [HeroiconMicro::class, 'heroicon-micro'],
    'Lucide' => [Lucide::class, 'lucide'],
]);

it('names cases after the icon and keeps the provider in the value', function (): void {
    expect(Heroicon::Home->value)->toBe('heroicon:home')
        ->and(HeroiconSolid::Home->value)->toBe('heroicon-solid:home')
        ->and(Lucide::House->value)->toBe('lucide:house')
        ->and(Lucide::GraduationCap->value)->toBe('lucide:graduation-cap')
        ->and(Alias::NavigationStudents->value)->toBe('navigation.students');
});

it('resolves alias cases through the configured aliases', function (): void {
    configureLuminaries(['presets' => ['framework', 'education'], 'aliases' => ['navigation.students' => 'lucide:users']]);

    expect(Luminaries::resolve(Alias::NavigationStudents)->canonical())->toBe('lucide:users')
        ->and(array_filter(Alias::cases(), static fn (Alias $alias): bool => ! Luminaries::exists($alias)))->toBe([]);
});

it('accepts typed references everywhere a name is accepted', function (): void {
    expect(Luminaries::exists(Heroicon::Home))->toBeTrue()
        ->and(Luminaries::tryResolve(Lucide::House)?->canonical())->toBe('lucide:house')
        ->and((string) Luminaries::render(Heroicon::Home, label: 'Home'))->toContain('data-gcego-icon="heroicon:home"')->toContain('aria-label="Home"')
        ->and((string) Luminaries::svg(Lucide::House, 'size-4'))->toContain('data-gcego-icon="lucide:house"')
        ->and(Blade::render('<x-gcego-icon :name="$icon" />', ['icon' => Heroicon::Home]))->toContain('data-gcego-icon="heroicon:home"')
        ->and(Blade::render('<x-gcego-icon-button :icon="$icon" label="Open" />', ['icon' => Heroicon::Home]))->toContain('data-gcego-icon="heroicon:home"');
});

it('accepts application enums of icon names', function (): void {
    configureLuminaries(['aliases' => ['school.terms' => 'heroicon:calendar']]);

    expect(Luminaries::resolve(SchoolIcon::Terms)->canonical())->toBe('heroicon:calendar')
        ->and(Blade::render('<x-gcego-icon :name="$icon" />', ['icon' => SchoolIcon::Logo]))->toContain('data-gcego-icon="custom:gcego-logo"');
});

it('refuses int-backed enums as icon names', function (): void {
    expect(Luminaries::tryResolve(Priority::High))->toBeNull()
        ->and(Luminaries::exists(Priority::High))->toBeFalse()
        ->and((string) Luminaries::svg(Priority::High))->toBe('')
        ->and(fn () => IconName::of(Priority::High))->toThrow(InvalidIconNameException::class, 'Priority::High');
});

it('turns icon and alias names into enum case names', function (string $name, string $case): void {
    expect(IconEnumGenerator::caseName($name))->toBe($case);
})->with([
    ['arrow-down-tray', 'ArrowDownTray'],
    ['navigation.breadcrumb-separator', 'NavigationBreadcrumbSeparator'],
    ['h1', 'H1'],
    ['3d-rotate', 'Icon3dRotate'],
    ['class', 'ClassIcon'],
    ['from', 'FromIcon'],
]);

it('writes the enums to a directory and reports them stale until regenerated', function (): void {
    $directory = sys_get_temp_dir().'/luminaries-enums-'.bin2hex(random_bytes(4));

    $this->artisan('gcego:icons:enums', ['--path' => $directory, '--check' => true])->assertFailed();
    $this->artisan('gcego:icons:enums', ['--path' => $directory])->assertSuccessful();

    expect(file_get_contents($directory.'/Heroicon.php'))->toBe(file_get_contents(dirname(__DIR__, 2).'/src/Icons/Heroicon.php'));

    file_put_contents($directory.'/Lucide.php', '<?php // edited by hand');

    $this->artisan('gcego:icons:enums', ['--path' => $directory, '--check' => true])
        ->expectsOutputToContain('Out of date: Lucide.')
        ->assertFailed();

    array_map(unlink(...), glob($directory.'/*.php') ?: []);
    rmdir($directory);
});

it('names the typed reference of bundled icons and aliases only', function (string $name, ?string $reference): void {
    expect(IconEnumGenerator::reference($name))->toBe($reference);
})->with([
    ['heroicon:home', 'Heroicon::Home'],
    ['heroicon-micro:bell', 'HeroiconMicro::Bell'],
    ['lucide:graduation-cap', 'Lucide::GraduationCap'],
    ['actions.edit', 'Alias::ActionsEdit'],
    ['custom:gcego-logo', null],
    ['school.terms', null],
    ['home', null],
    ['"><svg>', null],
]);

it('shows the typed reference in search results', function (): void {
    $this->artisan('gcego:icons:search', ['query' => 'graduation', '--provider' => 'lucide'])
        ->expectsOutputToContain('Lucide::GraduationCap')
        ->assertSuccessful();
});
