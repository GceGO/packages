<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use Gcego\Swatches\Contracts\BrandResolver;

/**
 * The default resolver: no tenant branding.
 */
final class NullBrandResolver implements BrandResolver
{
    public function brand(): ?Brand
    {
        return null;
    }
}
