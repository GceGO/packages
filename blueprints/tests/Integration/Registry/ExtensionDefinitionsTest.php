<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Blueprints\Tests\Fixtures\Extensions\ExtensionsServiceProvider;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Navigation;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Permissions;
use Gcego\Blueprints\Tests\Fixtures\PlainTable;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;

function withExtensions(): void
{
    test()->rebuildApplicationPublic(providers: [GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, ExtensionsServiceProvider::class]);
}

it('discovers blueprints that use definitions registered by other packages', function (): void {
    withExtensions();
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), \\".Navigation::class."::make('Academics', icon: 'book-open', sort: 2), \\".Permissions::class."::make(['view', 'create'])");

    $blueprint = app(Registry::class)->get('blueprint:subjects', Blueprint::class);

    expect($blueprint->find(Navigation::class)?->option('group'))->toBe('Academics')
        ->and($blueprint->find(Permissions::class)?->value())->toBe(['view', 'create'])
        ->and(app(Registry::class)->descriptor('blueprint:subjects')->metadata['definitions'])->toBe('name,navigation,permissions');
});

it('validates class options of extension definitions in the registry', function (): void {
    withExtensions();
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), \\".Navigation::class."::make('Academics', badge: \\App\\Missing\\Badge::class)");

    expect(fn () => app(Registry::class)->names())->toThrow(InvalidDefinitionException::class, 'App\\Missing\\Badge does not exist');
});

it('accepts an extension class option that exists', function (): void {
    withExtensions();
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), \\".Navigation::class."::make('Academics', badge: \\".PlainTable::class.'::class)');

    expect(app(Registry::class)->names())->toBe(['subjects']);
});

it('rejects an extension definition whose package is not installed', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), \\".Permissions::class."::make(['view'])");

    expect(fn () => app(Registry::class)->names())->toThrow(DefinitionLoadException::class, 'unsupported definition key "permissions"');
});
