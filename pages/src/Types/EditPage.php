<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Actions\Action;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\Edit;
use Gcego\Components\Property;

/**
 * A page that edits one record with a gcego/forms Form, saved through the
 * gcego/actions Edit action. The record is looked up in scope (tenant and
 * security scopes apply) and authorized ("update") when the page opens, on
 * every request of the page, and again by the action on submission:
 *
 *     new EditPage(
 *         new Property(EditPage::Name, 'subjects.edit'),
 *         new Property(EditPage::Title, 'Edit :record'),
 *         new Permission('subjects.update'),
 *         new Source(Subject::class),
 *         new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Subject Code')),
 *     )
 *
 * Path: /admin/subjects/{record}/edit. After a successful save the page
 * stays (RedirectTo: "self" by default) and shows the action's message.
 */
class EditPage extends FormPage
{
    public static function pageType(): string
    {
        return 'edit';
    }

    public static function formAction(): string
    {
        return 'edit';
    }

    protected static function defaultFormAction(): Action
    {
        return new Edit(new Property(Action::Placement, ActionPlacement::FormFooter));
    }

    protected static function recordPage(): bool
    {
        return true;
    }

    protected static function defaultRecordAbility(): string
    {
        return 'update';
    }
}
