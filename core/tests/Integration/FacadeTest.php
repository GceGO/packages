<?php

declare(strict_types=1);

use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Core\Facades\Gcego as GcegoFacade;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\FixturePackage;

it('forwards to the framework singleton', function (): void {
    expect(GcegoFacade::getFacadeRoot())->toBe(app(Gcego::class))
        ->and(GcegoFacade::isBooted())->toBeTrue()
        ->and(GcegoFacade::state())->toBe(FrameworkState::Booted)
        ->and(GcegoFacade::name())->toBe('GCEGO Studio')
        ->and(GcegoFacade::hasCapability('framework'))->toBeTrue()
        ->and(GcegoFacade::package('gcego/core')->id)->toBe('gcego/core')
        ->and(GcegoFacade::diagnose()->isHealthy())->toBeTrue();
});

it('boots manually and reports the same state as the injected framework', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(FixturePackage::make('acme/reports', capabilities: ['reports']));

    expect(GcegoFacade::isBooted())->toBeFalse()
        ->and(GcegoFacade::plan()->packages)->toBe(['acme/reports', 'gcego/core'])
        ->and(GcegoFacade::boot()->isBooted())->toBeTrue()
        ->and(app(Gcego::class)->isPackageActive('acme/reports'))->toBeTrue()
        ->and(GcegoFacade::capabilityProviders('reports'))->toBe(['packages' => ['acme/reports'], 'extensions' => []]);
});

it('serves the implementation bound to the framework contract', function (): void {
    $framework = Mockery::mock(FrameworkInterface::class);
    $framework->shouldReceive('hasCapability')->with('reports')->andReturnTrue();
    app()->instance(FrameworkInterface::class, $framework);
    GcegoFacade::clearResolvedInstance(FrameworkInterface::class);

    expect(GcegoFacade::hasCapability('reports'))->toBeTrue();
});

it('can be mocked in application tests', function (): void {
    GcegoFacade::shouldReceive('isPackageActive')->once()->with('acme/reports')->andReturnTrue();

    expect(GcegoFacade::isPackageActive('acme/reports'))->toBeTrue();
});

it('documents every framework method with its parameters', function (): void {
    preg_match_all('/@method static .+? (\w+)\((.*)\)$/m', (string) (new ReflectionClass(GcegoFacade::class))->getDocComment(), $documented, PREG_SET_ORDER);
    $documented = array_column(array_map(static fn (array $match): array => [$match[1], $match[2]], $documented), 1, 0);

    foreach ((new ReflectionClass(FrameworkInterface::class))->getMethods() as $method) {
        $parameters = array_map(static fn (ReflectionParameter $parameter): string => '$'.$parameter->getName(), $method->getParameters());
        preg_match_all('/\$\w+/', $documented[$method->getName()] ?? '', $documentedParameters);

        expect($documented)->toHaveKey($method->getName())
            ->and($documentedParameters[0])->toBe($parameters);
    }

    expect($documented)->toHaveCount(count((new ReflectionClass(FrameworkInterface::class))->getMethods()));
});

it('declares the facade alias for Laravel package discovery', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['extra']['laravel']['aliases'] ?? [])->toBe(['Gcego' => GcegoFacade::class]);
});
