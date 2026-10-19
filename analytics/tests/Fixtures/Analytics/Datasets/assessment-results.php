<?php

declare(strict_types=1);

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Analytics\Tests\Fixtures\Scopes\StudentsSeeTheirOwn;
use Gcego\Security\Requirements\Ability;

return new Dataset('assessment-results', AssessmentResult::class,
    new Ability('analytics.view'),
    new Field('score', 'Score', type: 'number'),
    new Field('time_spent', 'Time spent', type: 'integer', aggregates: ['sum', 'avg']),
    new Field('user_id', 'Student', type: 'integer', aggregates: ['count_distinct'], filterable: false),
    new Field('submitted_at', 'Submitted', type: 'datetime'),
    new Field('email', 'Email', new Ability('students.contact'), type: 'string', sensitive: true),
    new Dimension('subject_id', 'Subject', labelModel: Subject::class),
    new Dimension('level', 'Level', options: ['o' => 'Ordinary Level', 'a' => 'Advanced Level']),
    new Dimension('kind', 'Assessment type', options: ['objective' => 'Objective', 'structured' => 'Structured', 'essay' => 'Essay']),
    new Dimension('status', 'Status', options: ['started' => 'Started', 'completed' => 'Completed']),
    new Dimension('institution_id', 'Institution', new Ability('analytics.cross-institution')),
    dateColumn: 'submitted_at',
    scope: StudentsSeeTheirOwn::class,
);
