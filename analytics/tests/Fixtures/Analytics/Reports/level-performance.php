<?php

declare(strict_types=1);

use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportColumn;
use Gcego\Analytics\Reports\ReportFilter;
use Gcego\Security\Requirements\Ability;

// The drill-down target: by level, filtered to one subject; Admins only.
return new Report('level-performance', 'Performance by Level',
    new Ability('reports.levels'),
    new ReportColumn('level', 'Level'),
    new ReportColumn('attempts', 'Attempts', aggregate: 'count'),
    new ReportFilter('subject_id', 'Subject'),
    dataset: 'assessment-results',
);
