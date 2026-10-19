<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;

return new Metric(name: 'attempts_per_student', label: 'Attempts per student', aggregate: 'derived', formula: '{attempts} / {active_students}', precision: 2);
