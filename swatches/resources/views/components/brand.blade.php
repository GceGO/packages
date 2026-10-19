{{--
    <x-gcego-swatches::brand />                 The current tenant's brand (Contracts\BrandResolver), or nothing.
    <x-gcego-swatches::brand :brand="$brand" /> An explicit Gcego\Swatches\Branding\Brand.
    Place it after <x-gcego-swatches::theme> and before <x-gcego-swatches::accents>.
--}}
@props(['brand' => null, 'theme' => null, 'nonce' => null])
{{ app(\Gcego\Swatches\SwatchesManager::class)->brandTag($brand, $theme, $nonce) }}
