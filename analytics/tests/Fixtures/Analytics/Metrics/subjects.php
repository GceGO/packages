<?php

declare(strict_types=1);

use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Security\Requirements\Ability;

// A metric on a model (an implicit dataset), protected by its own requirement.
return new Metric('total_subjects', Subject::class, new Ability('analytics.view'), label: 'Total subjects');
