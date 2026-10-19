<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'completed_attempts', label: 'Completed attempts', dataset: 'assessment-results', filters: ['status' => 'completed']);
