<?php

declare(strict_types=1);

use Gcego\Components\Examples\Building\RevenueMetricBuilder;
use Gcego\Components\Examples\Components\RevenueMetric;
use Gcego\Components\Examples\Configurations\Padding;
use Gcego\Components\Facades\Components;

// Local example classes are outside the package's production autoload map.
// In your application, put these classes under App\... and use Composer autoloading.
require_once __DIR__.'/Values/Money.php';
require_once __DIR__.'/Configurations/Padding.php';
require_once __DIR__.'/Building/RevenueMetricBuilder.php';
require_once __DIR__.'/Components/RevenueMetric.php';

// One-off registration for this demonstration's current scope.
// Production applications register aliases via a service provider afterResolving callback.
Components::catalog()->register('revenueMetric', RevenueMetric::class);
$metric = Components::component('revenueMetric', 'revenue');

if (! $metric instanceof RevenueMetricBuilder) {
    throw new RuntimeException('The custom component must resolve its declared builder.');
}

return Components::schema([
    $metric->label('Revenue')->money(250000, 'XAF')->highlight()->configure(new Padding(16)),
])->assertValid();
