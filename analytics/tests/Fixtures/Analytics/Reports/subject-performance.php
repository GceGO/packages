<?php

declare(strict_types=1);

use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportColumn;
use Gcego\Analytics\Reports\ReportFilter;
use Gcego\Security\Requirements\Ability;

return new Report('subject-performance', 'Subject Performance',
    new Ability('reports.academic'),
    new ReportColumn('subject_id', 'Subject', drillDown: 'level-performance'),
    new ReportColumn('attempts', 'Attempts', aggregate: 'count'),
    new ReportColumn('students', 'Students', aggregate: 'count_distinct', field: 'user_id'),
    new ReportColumn('average_score', 'Average score', aggregate: 'avg', field: 'score', precision: 1),
    new ReportColumn('contacts', 'Distinct emails', aggregate: 'count_distinct', field: 'email'),
    new ReportFilter('level', 'Level'),
    new ReportFilter('kind', 'Assessment type', multiple: true),
    new ReportFilter('score', 'Minimum score', operator: 'gte'),
    dataset: 'assessment-results',
    sort: 'average_score',
    perPage: 2,
    export: true,
    schedulable: true,
    formats: ['csv', 'xlsx'],
    exportAbility: 'analytics.export',
);
