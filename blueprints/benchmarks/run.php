<?php

declare(strict_types=1);

/**
 * Repeatable micro-benchmark for gcego/blueprints.
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [blueprints] [iterations])
 *
 * Each blueprint declares the full Subjects shape: 4 scalar or model
 * definitions, 3 single references and 4 collections (14 class references).
 */

use App\GCEGo\Modules\Academics\Subjects\Actions\DeleteSubject;
use App\GCEGo\Modules\Academics\Subjects\Actions\ExportSubjects;
use App\GCEGo\Modules\Academics\Subjects\Forms\SubjectForm;
use App\GCEGo\Modules\Academics\Subjects\Infolists\SubjectInfolist;
use App\GCEGo\Modules\Academics\Subjects\Pages\CreateSubject;
use App\GCEGo\Modules\Academics\Subjects\Pages\EditSubject;
use App\GCEGo\Modules\Academics\Subjects\Pages\ListSubjects;
use App\GCEGo\Modules\Academics\Subjects\Pages\ViewSubject;
use App\GCEGo\Modules\Academics\Subjects\Relations\SyllabiRelation;
use App\GCEGo\Modules\Academics\Subjects\Relations\TopicsRelation;
use App\GCEGo\Modules\Academics\Subjects\Tables\SubjectsTable;
use App\GCEGo\Modules\Academics\Subjects\Widgets\SubjectsOverview;
use App\GCEGo\Modules\Academics\Subjects\Widgets\SubjectsStatistics;
use App\Models\Subject;
use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintCollection;
use Gcego\Blueprints\Definitions\Actions;
use Gcego\Blueprints\Definitions\Description;
use Gcego\Blueprints\Definitions\Form;
use Gcego\Blueprints\Definitions\Infolist;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Relations;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Definitions\Widgets;
use Gcego\Blueprints\Validation\BlueprintValidator;

require __DIR__.'/../vendor/autoload.php';

$count = max(1, (int) ($argv[1] ?? 300));
$iterations = max(1, (int) ($argv[2] ?? 10));

/**
 * @return list<Blueprint>
 */
$build = static function () use ($count): array {
    $blueprints = [];

    for ($i = 0; $i < $count; $i++) {
        $blueprints[] = Blueprint::define(
            Name::make('feature-'.$i),
            Title::make('Feature '.$i),
            Description::make('Manage feature '.$i),
            Model::make(Subject::class),
            Table::make(SubjectsTable::class),
            Form::make(SubjectForm::class),
            Infolist::make(SubjectInfolist::class),
            Pages::make([ListSubjects::class, CreateSubject::class, EditSubject::class, ViewSubject::class]),
            Actions::make([DeleteSubject::class, ExportSubjects::class]),
            Relations::make([TopicsRelation::class, SyllabiRelation::class]),
            Widgets::make([SubjectsOverview::class, SubjectsStatistics::class]),
        );
    }

    return $blueprints;
};

$measure = static function (string $label, Closure $operation) use ($iterations): void {
    $operation();
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $operation();
    }

    printf("%-34s %9.3f ms/op\n", $label, (hrtime(true) - $start) / 1e6 / $iterations);
};

printf("gcego/blueprints benchmark: %d blueprints x 11 definitions, %d iterations, PHP %s\n\n", $count, $iterations, PHP_VERSION);

$measure('define', $build);

$blueprints = $build();

$measure('read accessors', static function () use ($blueprints): void {
    foreach ($blueprints as $blueprint) {
        $blueprint->title();
        $blueprint->table();
        $blueprint->pages();
        $blueprint->widgets();
    }
});

$validator = new BlueprintValidator;
$measure('validate references (no rules)', static function () use ($blueprints, $validator): void {
    foreach ($blueprints as $blueprint) {
        $validator->validate($blueprint);
    }
});

$measure('normalize (toArray)', static function () use ($blueprints): void {
    foreach ($blueprints as $blueprint) {
        $blueprint->toArray();
    }
});

$measure('collect into BlueprintCollection', static fn () => new BlueprintCollection(...$blueprints));

printf("\nPeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);
