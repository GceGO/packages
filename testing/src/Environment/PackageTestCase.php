<?php

declare(strict_types=1);

namespace Gcego\Testing\Environment;

use Gcego\Testing\Concerns\InteractsWithGcego;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * A ready Orchestra Testbench test case for GCEGO packages and extensions:
 * every installed GCEGO provider, the isolated configuration of
 * TestApplicationFactory, the toolkit's assertions, and state reset after
 * each test.
 *
 * Override gcegoProviders() to limit the providers, gcegoConfiguration()
 * to change configuration, and defineDatabaseMigrations() (Testbench) for
 * your schema. Requires orchestra/testbench.
 */
abstract class PackageTestCase extends TestCase
{
    use InteractsWithGcego;

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function gcegoProviders(): array
    {
        return TestApplicationFactory::providers();
    }

    /**
     * @return array<string, mixed>
     */
    protected function gcegoConfiguration(): array
    {
        return [];
    }

    protected function getPackageProviders($app): array
    {
        return $this->gcegoProviders();
    }

    protected function defineEnvironment($app): void
    {
        TestApplicationFactory::configure($app['config'], $this->gcegoConfiguration());
    }

    protected function tearDown(): void
    {
        $this->resetGcegoState();

        parent::tearDown();
    }
}
