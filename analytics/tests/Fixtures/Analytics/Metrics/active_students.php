<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'active_students', label: 'Active students', dataset: 'assessment-results', aggregate: 'count_distinct', field: 'user_id');
