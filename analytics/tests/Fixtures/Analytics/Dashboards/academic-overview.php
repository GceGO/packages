<?php

declare(strict_types=1);

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Dashboards\Chart;
use Gcego\Analytics\Dashboards\Kpi;
use Gcego\Analytics\Dashboards\ReportSection;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\ReportFilter;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Security\Requirements\Ability;

return new Dashboard('academic-overview',
    new Ability('analytics.view'),
    new Metric(name: 'subjects_offered', label: 'Subjects offered', model: Subject::class, aggregate: 'count'),
    new Kpi('attempts'),
    new Kpi('average_score'),
    new Kpi('completion_rate'),
    new Chart('attempts-over-time', 'Attempts over time', metric: 'daily_attempts', interval: 'day', compare: true),
    new Chart('score-by-subject', 'Average score by subject', metric: 'average_score', groupBy: 'subject_id', type: 'bar'),
    new ReportSection('subject-performance'),
    new ReportFilter('level', 'Level'),
    title: 'Academic Overview',
    description: 'Assessments across subjects and levels.',
    defaultRange: 'last_30_days',
);
