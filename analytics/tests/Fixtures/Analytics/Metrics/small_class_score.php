<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'small_class_score', label: 'Average score (suppressed)', dataset: 'small-classes', aggregate: 'avg', field: 'score');
