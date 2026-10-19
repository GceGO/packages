<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Pages\Page;

/**
 * A read-only record page around a gcego/infolists Infolist (field read
 * rules, formatting and relationships come from the infolist engine). The
 * record is looked up in scope and authorized ("view"):
 *
 *     new ViewPage(
 *         new Property(ViewPage::Name, 'subjects.view'),
 *         new Property(ViewPage::Title, ':record'),
 *         new Permission('subjects.view'),
 *         new Source(Subject::class),
 *         new PageLink('Edit', 'subjects.edit'),
 *         new Delete,
 *         new Infolist(new Text('name', 'Subject Name'), new Text('code', 'Subject Code')),
 *     )
 *
 * Path: /admin/subjects/{record} (a last segment "view" or "show" is dropped).
 */
class ViewPage extends Page
{
    public static function pageType(): string
    {
        return 'view';
    }

    public static function requiredRole(): string
    {
        return BlueprintRole::Infolist;
    }

    protected static function recordPage(): bool
    {
        return true;
    }
}
