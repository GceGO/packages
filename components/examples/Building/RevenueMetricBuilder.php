<?php

declare(strict_types=1);

namespace Gcego\Components\Examples\Building;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Examples\Values\Money;

final class RevenueMetricBuilder extends ComponentBuilder
{
    public function money(int $minorUnits, string $currency): static
    {
        return $this->set('amount', new Money($minorUnits, $currency));
    }
}
