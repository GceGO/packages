<?php

declare(strict_types=1);

namespace App\GCEGo\Modules\Academics\Subjects;

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
use Gcego\Blueprints\Registry\BlueprintProvider;
use Gcego\Blueprints\Registry\ProvidesBlueprint;

final class Subjects implements BlueprintProvider
{
    use ProvidesBlueprint;

    public function blueprint(): Blueprint
    {
        return Blueprint::define(
            Name::make('subjects'),
            Title::make('Subject Management'),
            Description::make('Manage academic subjects'),
            Model::make(Subject::class),

            Table::make(SubjectsTable::class),
            Form::make(SubjectForm::class),
            Infolist::make(SubjectInfolist::class),

            Pages::make([
                ListSubjects::class,
                CreateSubject::class,
                EditSubject::class,
                ViewSubject::class,
            ]),

            Actions::make([
                DeleteSubject::class,
                ExportSubjects::class,
            ]),

            Relations::make([
                TopicsRelation::class,
                SyllabiRelation::class,
            ]),

            Widgets::make([
                SubjectsOverview::class,
                SubjectsStatistics::class,
            ]),
        );
    }
}
