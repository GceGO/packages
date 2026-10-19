<?php

declare(strict_types=1);

use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\Contracts\BrandResolver;
use Gcego\Swatches\SwatchesManager;
use Illuminate\Http\Request;

/**
 * A resolver that reads the tenant from the current request, like an application would.
 */
final class RequestBrandResolver implements BrandResolver
{
    public function brand(): ?Brand
    {
        $color = request()->header('X-Tenant-Color');

        return is_string($color) ? Brand::fromArray(['primary' => $color]) : null;
    }
}

it('never carries one tenant\'s brand into the next request', function (): void {
    app()->scoped(BrandResolver::class, RequestBrandResolver::class);
    $swatches = app(SwatchesManager::class);

    $this->app->instance('request', Request::create('/', server: ['HTTP_X_TENANT_COLOR' => '#2563eb']));
    $first = (string) $swatches->brandTag();

    // What Octane and the queue worker do between requests.
    $this->app->forgetScopedInstances();
    $this->app->instance('request', Request::create('/'));
    $second = (string) $swatches->brandTag();

    $this->app->forgetScopedInstances();
    $this->app->instance('request', Request::create('/', server: ['HTTP_X_TENANT_COLOR' => '#be123c']));
    $third = (string) $swatches->brandTag();

    expect($first)->toContain('#2563eb')
        ->and($second)->toBe('')
        ->and($third)->toContain('#be123c')
        ->and($third)->not->toContain('#2563eb');
});

it('compiles explicit brands independently through the shared manager', function (): void {
    $swatches = app(SwatchesManager::class);
    $blue = (string) $swatches->brandTag(Brand::fromArray(['primary' => '#2563eb']));
    $red = (string) $swatches->brandTag(Brand::fromArray(['primary' => '#be123c']));

    expect($blue)->not->toBe($red)
        ->and((string) $swatches->brandTag(Brand::fromArray(['primary' => '#2563eb'])))->toBe($blue)
        ->and((string) $swatches->themeTag())->toBe('');
});
