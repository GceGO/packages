<?php

declare(strict_types=1);

use Gcego\Panels\Panel;
use Gcego\Panels\Registry\PanelKind;
use Gcego\Panels\Tests\Fixtures\Access\StaffAccess;

/**
 * A README example, evaluated as written (App\Panels\SuperAdminAccess is a fixture).
 */
function readmePanel(string $name): Panel
{
    if (! class_exists('App\\Panels\\SuperAdminAccess')) {
        class_alias(StaffAccess::class, 'App\\Panels\\SuperAdminAccess');
    }

    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $imports = <<<'PHP'
        use Gcego\Components\Properties\Property;
        use Gcego\Panels\Branding\Branding;
        use Gcego\Panels\Definitions\ModuleReference;
        use Gcego\Panels\Definitions\PageReference;
        use Gcego\Panels\Menu\UserMenuItem;
        use Gcego\Panels\Panel;
        use Gcego\Security\Requirements\Authenticated;
        use Gcego\Security\Requirements\TenantMember;
        PHP;
    $panel = eval($imports."\n".($match[1] ?? 'return null;'));

    return $panel instanceof Panel ? $panel : throw new RuntimeException('Example '.$name.' did not return a Panel.');
}

it('validates every README example', function (string $example, string $path, bool $tenant): void {
    $panel = readmePanel($example);

    expect((new PanelKind)->validate($panel))->toBe([])
        ->and($panel->path())->toBe($path)
        ->and($panel->declaresAccess())->toBeTrue()
        ->and($panel->tenant() === 'required')->toBe($tenant);
})->with([
    ['super-admin', 'platform', false],
    ['school', 'school', true],
    ['staff', '', false],
]);

it('routes and serves the documented shapes', function (): void {
    $world = world();

    expect(panels()->url('school', ['institution' => 2]))->toBe('/school/2')
        ->and(panels()->pageUrl('admin', 'reports'))->toBe('/admin/reports')
        ->and(route('gcego.admin.academics.subjects.index', [], false))->toBe('/admin/subjects')
        ->and(route('gcego.school.academics.subjects.view', ['institution' => 1, 'record' => 1], false))->toBe('/school/1/subjects/1');

    asMember($world['admin'])->get('/admin')->assertRedirect('/admin/overview');
});
