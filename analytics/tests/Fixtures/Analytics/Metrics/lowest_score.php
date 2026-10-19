<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'lowest_score', label: 'Lowest score', dataset: 'assessment-results', aggregate: 'min', field: 'score');
