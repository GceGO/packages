<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Blueprints\Registry\BlueprintKind;
use Gcego\Blueprints\Tests\Fixtures\ModulesStandIn\ModulesStandInServiceProvider;
use Gcego\Blueprints\Tests\Fixtures\ModulesStandIn\ModuleStandIn;
use Gcego\Blueprints\Tests\Fixtures\ModulesStandIn\ModuleStandInKind;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;

/*
 * gcego/modules cannot be installed next to this version of gcego/blueprints
 * (its dependencies still use the removed component-based API), so these tests
 * use ModulesStandInServiceProvider, which reproduces its registry wiring.
 */

/**
 * @return array<string, list<class-string>>
 */
function providerOrders(): array
{
    $providers = ['registry' => RegistryServiceProvider::class, 'blueprints' => BlueprintsServiceProvider::class, 'modules' => ModulesStandInServiceProvider::class];
    $orders = [];

    foreach (['registry', 'blueprints', 'modules'] as $first) {
        foreach (array_diff(['registry', 'blueprints', 'modules'], [$first]) as $second) {
            $third = array_values(array_diff(['registry', 'blueprints', 'modules'], [$first, $second]))[0];
            $orders["{$first}, {$second}, {$third}"] = [GcegoServiceProvider::class, $providers[$first], $providers[$second], $providers[$third]];
        }
    }

    return $orders;
}

it('discovers modules and blueprints from one folder in any provider order', function (string $first, string $second, string $third, string $fourth): void {
    $modules = $this->workspace->path('Modules');
    $this->workspace->file('Modules/Academics/Module.php', '<?php return new '.ModuleStandIn::class."('academics');");
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    $this->rebuildApplication(['gcego-modules.discovery.paths' => [$modules]], providers: [$first, $second, $third, $fourth]);
    $registry = app(Registry::class);

    expect(app(DefinitionKinds::class)->get('module'))->toBeInstanceOf(ModuleStandInKind::class)
        ->and(app(DefinitionKinds::class)->get('blueprint'))->toBeInstanceOf(BlueprintKind::class)
        ->and($registry->names('module'))->toBe(['academics'])
        ->and($registry->names('blueprint'))->toBe(['subjects'])
        ->and($registry->get('blueprint:subjects'))->toBeInstanceOf(Blueprint::class)
        ->and($registry->failures())->toBe([]);
})->with(providerOrders());

it('lets the same name exist as a module and a blueprint', function (): void {
    $this->workspace->file('Modules/Subjects/Module.php', '<?php return new '.ModuleStandIn::class."('subjects');");
    $this->workspace->blueprintModule('Modules/Subjects/Catalog', "Name::make('subjects')");

    $this->rebuildApplication(['gcego-modules.discovery.paths' => [$this->workspace->path('Modules')]], providers: [GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, ModulesStandInServiceProvider::class]);

    expect(app(Registry::class)->has('module:subjects'))->toBeTrue()
        ->and(app(Registry::class)->has('blueprint:subjects'))->toBeTrue();
});

it('never treats a folder named Module as a blueprint entrypoint', function (): void {
    $this->workspace->file('Modules/Module/Module.php', '<?php return new '.ModuleStandIn::class."('core');");

    $this->rebuildApplication(['gcego-modules.discovery.paths' => [$this->workspace->path('Modules')]], providers: [GcegoServiceProvider::class, BlueprintsServiceProvider::class, RegistryServiceProvider::class, ModulesStandInServiceProvider::class]);

    expect(app(Registry::class)->names('module'))->toBe(['core'])
        ->and(app(Registry::class)->names('blueprint'))->toBe([]);
});

it('discovers blueprints under the registry default module root when no module kind is registered', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    $this->rebuildApplication(['gcego-registry.discovery.roots' => [['path' => $this->workspace->path('Modules'), 'kind' => 'module', 'entrypoint' => 'directory']]]);

    expect(app(Registry::class)->names('blueprint'))->toBe(['subjects'])
        ->and(app(Registry::class)->failures())->toBe([]);
});

it('gives a blueprint entrypoint to the blueprint kind when the registry default module root belongs to gcego/modules', function (string $first, string $second, string $third, string $fourth): void {
    $modules = $this->workspace->path('Modules');
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    $this->rebuildApplication([
        'gcego-registry.discovery.roots' => [['path' => $modules, 'kind' => 'module', 'entrypoint' => 'directory']],
        'gcego-modules.discovery.paths' => [$modules],
    ], providers: [$first, $second, $third, $fourth]);

    expect(app(Registry::class)->get('blueprint:subjects'))->toBeInstanceOf(Blueprint::class)
        ->and(app(Registry::class)->has('module:subjects'))->toBeFalse()
        ->and(app(Registry::class)->failures())->toBe([]);
})->with(providerOrders());
