<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'completion_ratio', label: 'Completion ratio', aggregate: 'ratio', numerator: 'completed_attempts', denominator: 'attempts', precision: 3);
