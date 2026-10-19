<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'best_score', label: 'Best score', dataset: 'assessment-results', aggregate: 'max', field: 'score');
