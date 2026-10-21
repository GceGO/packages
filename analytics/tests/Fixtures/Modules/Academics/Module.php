<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Analytics\Tests\Fixtures\Actions\ExportSubjectPerformance;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academics'),

    new Resource(
        new Source(Subject::class),
        new Property(Resource::RecordTitle, 'name'),
        new Property(Resource::RecordKey, 'id'),
        new Table(new Text('name', 'Subject')),
        new Action('export-performance', 'Export performance', new Handler(ExportSubjectPerformance::class), new Property(Action::Record, false), new Property(Action::Ability, 'viewAny'),
            new Input(['level' => ['nullable', 'in:o,a']]),
        ),
    ),
);
