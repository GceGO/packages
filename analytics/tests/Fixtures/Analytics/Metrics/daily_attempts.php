<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'daily_attempts', label: 'Daily attempts', dataset: 'assessment-results', interval: 'day');
