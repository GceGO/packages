<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'average_score', label: 'Average score', dataset: 'assessment-results', aggregate: 'avg', field: 'score', precision: 1);
