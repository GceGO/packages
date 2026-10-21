<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Actions\Bulk\BulkPolicy;
use Gcego\Actions\BulkAction;
use Gcego\Actions\BulkDelete;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\ForceDelete;
use Gcego\Actions\Restore;
use Gcego\Actions\Tests\Fixtures\Models\Course;
use Gcego\Actions\Tests\Fixtures\Models\Student;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Actions\View;
use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;

/**
 * The definitions an application (or a future gcego/modules package) would register.
 */
final class Blueprints
{
    public static function subjects(): Blueprint
    {
        $fields = new Input(['name' => ['required', 'string', 'max:120'], 'code' => ['nullable', 'string', 'max:20'], 'is_published' => ['boolean']]);

        return new Blueprint(
            'subjects',
            new Ability('viewAny', Subject::class),
            new Source(Subject::class),
            new Create($fields, new FieldRule('is_published', write: new Permission('subjects.publish'))),
            new Edit($fields),
            new View,
            new Delete,
            new Restore,
            new ForceDelete,
            new Action('publish', 'Publish', new Property(Action::Ability, 'publish'), new Property(Action::Record, true), new Handler(PublishSubject::class)),
            new Action('rename', 'Rename', new Property(Action::Ability, 'update'), new Property(Action::Record, true), new Property(Action::Confirmation, true), new Input(['name' => ['required', 'string']]), new Handler(fn (ActionContext $context) => $context->model()->update(['name' => $context->input->get('name')]))),
            new Action('ungoverned', 'Ungoverned', new Handler(fn () => true)),
            new Action('secret', 'Secret', new Property(Action::Hidden, true), new Property(Action::Ability, 'viewAny'), new Handler(fn () => true)),
            new Action('frozen', 'Frozen', new Property(Action::Disabled, true), new Property(Action::Ability, 'viewAny'), new Handler(fn () => true)),
            new Action('styled', 'Styled', new Property(Action::Ability, 'viewAny'), new Handler(fn () => true), new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-full', 'bg-white']))),
            new BulkDelete,
            new BulkAction('bulk-publish', 'Publish selected', new Property(BulkAction::Ability, 'publish'), new Property(BulkAction::Policy, BulkPolicy::Partial->value), new Property(BulkAction::Confirmation, false), new Handler(fn (ActionContext $context) => $context->model()->getAttribute('code') === 'BROKEN' ? throw new \RuntimeException('broken') : $context->model()->update(['is_published' => true]))),
            new ActionGroup('more', 'More', new Action('archive', 'Archive', new Property(Action::Record, true), new Permission('subjects.archive'), new Handler(fn (ActionContext $context) => $context->model()->update(['code' => 'ARCHIVED'])))),
        );
    }

    public static function students(): Blueprint
    {
        return new Blueprint('students', new Ability('viewAny', Student::class), new Source(Student::class), new Edit(new Input(['name' => ['required', 'string']])), new Delete(new Property(Delete::Confirmation, false)));
    }

    public static function courses(): Blueprint
    {
        return new Blueprint('courses', new Ability('viewAny', Course::class), new Source(Course::class), new Edit(new Input(['title' => ['required', 'string']])), new Delete(new Property(Delete::Confirmation, false)));
    }

    public static function other(): Blueprint
    {
        return new Blueprint('reports', new Ability('viewAny', Subject::class), new Source(Subject::class), new Action('export', 'Export', new Property(Action::Ability, 'viewAny'), new Handler(fn () => true)));
    }
}
