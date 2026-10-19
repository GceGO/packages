<?php

declare(strict_types=1);

namespace Gcego\Testing\Concerns;

use Closure;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\TestingManager;

/**
 * PHPUnit-style assertions for any Laravel or Testbench test case. They
 * run the same testables as GcegoTest, so failures read the same.
 */
trait InteractsWithGcego
{
    use InteractsWithGcegoFixtures;
    use InteractsWithGcegoLivewire;
    use InteractsWithGcegoSecurity;
    use InteractsWithGcegoTenancy;

    protected function gcego(): TestingManager
    {
        return GcegoTest::manager();
    }

    protected function resetGcegoState(): void
    {
        if (isset($this->app)) {
            GcegoTest::reset();
        }
    }

    public function assertGcegoComponentValid(ComponentInterface $component): void
    {
        $this->gcego()->component($component)->assertValid();
    }

    public function assertGcegoComponentInvalid(ComponentInterface $component, ?string $code = null, ?string $path = null): void
    {
        $this->gcego()->component($component)->assertInvalid($code, $path);
    }

    public function assertGcegoBlueprintValid(BlueprintInterface $blueprint): void
    {
        $this->gcego()->blueprint($blueprint)->assertValid();
    }

    public function assertGcegoBlueprintHas(BlueprintInterface $blueprint, string $role): void
    {
        $this->gcego()->blueprint($blueprint)->assertHasComponent($role);
    }

    /**
     * @param  list<string>  $except
     */
    public function assertGcegoAccessible(string $html, array $except = []): void
    {
        $this->gcego()->accessibility($html)->assertAccessible($except);
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function assertGcegoQueryCountAtMost(int $limit, Closure $callback): void
    {
        $this->gcego()->performance()->measure($callback)->assertQueryCountAtMost($limit);
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function assertGcegoNoRepeatedQueries(Closure $callback, ?int $threshold = null): void
    {
        $this->gcego()->performance()->measure($callback)->assertNoRepeatedQueries($threshold);
    }
}
