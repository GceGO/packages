<?php

declare(strict_types=1);

namespace Gcego\Testing\Concerns;

use Gcego\Testing\Fixtures\DefinitionFactory;
use Gcego\Testing\Fixtures\FixtureManager;
use Gcego\Testing\Fixtures\Workspace;
use Gcego\Testing\GcegoTest;
use Illuminate\Contracts\Auth\Authenticatable;

trait InteractsWithGcegoFixtures
{
    protected function gcegoFixtures(): FixtureManager
    {
        return GcegoTest::fixtures();
    }

    protected function gcegoWorkspace(): Workspace
    {
        return GcegoTest::fixtures()->workspace();
    }

    protected function gcegoDefinitions(): DefinitionFactory
    {
        return GcegoTest::fixtures()->definitions();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createGcegoUser(array $attributes = []): Authenticatable
    {
        return GcegoTest::fixtures()->users()->create($attributes);
    }
}
