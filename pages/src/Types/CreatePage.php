<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Actions\Action;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\Create;
use Gcego\Components\Properties\Property;

/**
 * A page with a gcego/forms Form that creates a record through the
 * gcego/actions Create action (validated, writable input only; ability
 * "create"; in a transaction):
 *
 *     new CreatePage(
 *         new Property(CreatePage::Name, 'subjects.create'),
 *         new Property(CreatePage::Title, 'Create Subject'),
 *         new Permission('subjects.create'),
 *         new Source(Subject::class),
 *         new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Subject Code')),
 *     )
 *
 * Path: /admin/subjects/create. After a successful save the page goes to
 * RedirectTo (default: the view page of the new record, else its edit page,
 * else the index page).
 */
class CreatePage extends FormPage
{
    public static function pageType(): string
    {
        return 'create';
    }

    public static function formAction(): string
    {
        return 'create';
    }

    protected static function defaultFormAction(): Action
    {
        return new Create(new Property(Action::Placement, ActionPlacement::FormFooter));
    }

    protected static function recordPage(): bool
    {
        return false;
    }
}
