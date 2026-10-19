<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'total_time', label: 'Time spent', dataset: 'assessment-results', aggregate: 'sum', field: 'time_spent', format: 'duration');
