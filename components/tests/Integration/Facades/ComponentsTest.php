<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\ComponentManager;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Components\Exceptions\InvalidChildException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Traversal\ComponentWalker;
use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

it('loads the required provider on the first facade call', function (): void {
    $app = Container::getInstance();

    expect($app->getLoadedProviders())->not->toHaveKey(ComponentServiceProvider::class);
    $component = Components::make(items: [new Property(Component::Name, 'dashboard')]);

    expect($component->name())->toBe('dashboard')
        ->and($app->getLoadedProviders())->toHaveKey(ComponentServiceProvider::class)
        ->and(Components::getFacadeRoot())->toBe($app->make(ComponentManager::class));
});

it('proxies construction validation normalization inspection and trees to the manager', function (): void {
    $component = Components::define(ExampleComponent::class, ['name', 'Label'])->searchable()->build();
    $manager = Components::getFacadeRoot();

    expect(Components::validate($component)->isValid())->toBeTrue()
        ->and(Components::normalize($component))->toBe($manager->normalize($component))
        ->and(Components::inspect($component)->toArray())->toBe($manager->inspect($component)->toArray())
        ->and(Components::tree($component)->root())->toBe($component)
        ->and(Components::make(ExampleComponent::class, ['other'])->name())->toBe('other');
});

it('uses configured services from both the facade and component methods', function (): void {
    $app = Container::getInstance();
    $app->bind(ComponentNormalizer::class, static fn (Container $container): ComponentNormalizer => new ComponentNormalizer(
        $container->make(ComponentWalker::class), includeSchema: true,
    ));
    $root = Components::make();

    expect(Components::normalize($root))->toHaveKey('schema')
        ->and($root->normalize())->toHaveKey('schema');

    $app->instance(ComponentWalker::class, new ComponentWalker(maxDepth: 0));
    $nested = Components::make(items: [new Component]);

    expect(fn () => Components::validate($nested))->toThrow(InvalidChildException::class)
        ->and(fn () => $nested->validate())->toThrow(InvalidChildException::class);
});

it('rejects an unregistered application even when an earlier facade root was cached', function (): void {
    Components::make();
    $app = new Application(dirname(__DIR__, 3));
    Facade::setFacadeApplication($app);

    expect(fn () => Components::make())->toThrow(InvalidConfigurationException::class, 'ComponentServiceProvider must be registered');
});

it('reports an unbooted facade application clearly', function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);

    expect(fn () => Components::make())->toThrow(InvalidConfigurationException::class, 'boot Laravel facades');
});

it('resolves the Components alias advertised for Laravel discovery', function (): void {
    $manifest = json_decode(file_get_contents(dirname(__DIR__, 3).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $aliases = $manifest['extra']['laravel']['aliases'];

    expect($aliases)->toBe(['Components' => Components::class]);
    AliasLoader::getInstance($aliases)->load('Components');

    expect(\Components::make()->validate()->isValid())->toBeTrue()
        ->and(\Components::getFacadeRoot())->toBe(Components::getFacadeRoot());
});

it('resolves the manager from the active registered application without a second facade cache', function (): void {
    $original = Components::getFacadeRoot();
    $app = new Application(dirname(__DIR__, 3));
    Facade::setFacadeApplication($app);
    $app->register(ComponentServiceProvider::class);
    $app->bind(ComponentNormalizer::class, static fn (Container $container): ComponentNormalizer => new ComponentNormalizer(
        $container->make(ComponentWalker::class), includeSchema: true,
    ));

    expect(Components::getFacadeRoot())->not->toBe($original)
        ->and(Components::getFacadeRoot())->toBe($app->make(ComponentManager::class))
        ->and(Components::make()->normalize())->toHaveKey('schema');
});

it('requires registration for every layout factory and collection entry point', function (): void {
    Facade::setFacadeApplication(new Application(dirname(__DIR__, 3)));

    foreach ([
        static fn () => Components::schema([]),
        static fn () => Component::collect([]),
        static fn () => Component::section('dashboard'),
        static fn () => Component::group('metrics'),
        static fn () => Component::item('statistics'),
    ] as $operation) {
        expect($operation)->toThrow(InvalidConfigurationException::class, 'ComponentServiceProvider must be registered');
    }
});
