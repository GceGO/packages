<?php

declare(strict_types=1);

namespace Gcego\Core\Support;

use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Packages\PackageDescriptor;

/**
 * Finds capabilities that a provider declared exclusive while another provider also offers them.
 *
 * Ordinary capabilities may have any number of providers. A capability becomes
 * exclusive as soon as one active provider declares it so; from then on exactly
 * one active provider is allowed.
 *
 * @internal
 */
final class ExclusiveCapabilities
{
    /**
     * @param  iterable<PackageDescriptor|ExtensionDescriptor>  $providers  The providers that will be active together.
     * @return array<string, list<string>> Conflicting capability => sorted provider ids, sorted by capability.
     */
    public static function conflicts(iterable $providers): array
    {
        $exclusive = [];
        $providersByCapability = [];

        foreach ($providers as $provider) {
            foreach ($provider->exclusiveCapabilities as $capability) {
                $exclusive[$capability] = true;
            }

            foreach ($provider->capabilities as $capability) {
                $providersByCapability[$capability][] = $provider->id;
            }
        }

        $conflicts = [];

        foreach ($providersByCapability as $capability => $ids) {
            if (isset($exclusive[$capability]) && count($ids) > 1) {
                sort($ids);
                $conflicts[$capability] = $ids;
            }
        }

        ksort($conflicts);

        return $conflicts;
    }
}
