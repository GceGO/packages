<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'completion_rate', label: 'Completion rate', aggregate: 'percentage', numerator: 'completed_attempts', denominator: 'attempts', precision: 1);
