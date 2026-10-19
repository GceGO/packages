<?php

declare(strict_types=1);

namespace Gcego\Swatches\Contracts;

use Gcego\Swatches\Branding\Brand;

/**
 * Supplies the current request's tenant brand, if any.
 *
 * Bind your implementation in a service provider; Swatches registers it as a
 * scoped binding (fresh per request and per Octane/queue job), so a brand is
 * never carried from one request to the next:
 *
 *     $this->app->scoped(BrandResolver::class, CurrentInstitutionBrand::class);
 *
 * Build the Brand with Brand::fromArray() from stored institution settings; it
 * rejects anything that is not a color or a theme name.
 */
interface BrandResolver
{
    public function brand(): ?Brand;
}
