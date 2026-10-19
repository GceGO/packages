<?php

declare(strict_types=1);

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Security\Requirements\Ability;

// The same data with small-cell suppression (groups under 3 attempts are hidden).
return new Dataset('small-classes', AssessmentResult::class,
    new Ability('analytics.view'),
    new Field('score', 'Score', type: 'number'),
    new Dimension('subject_id', 'Subject', labelModel: Subject::class),
    dateColumn: 'submitted_at',
    minimumGroupSize: 3,
);
