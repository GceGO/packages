<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Pages\Page;

/**
 * A list page around one gcego/tables Table (search, filters, sorting,
 * pagination, row and bulk actions all come from the table engine):
 *
 *     new IndexPage(
 *         new Property(IndexPage::Name, 'subjects'),
 *         new Property(IndexPage::Title, 'Subjects'),
 *         new Permission('subjects.view'),
 *         new Source(Subject::class),
 *         new PageLink('New subject', 'subjects.create'),
 *         new Table(new Text('name', 'Subject Name'), new Text('code', 'Code')),
 *     )
 *
 * Path: the name's segments ("subjects" -> /admin/subjects).
 */
class IndexPage extends Page
{
    public static function pageType(): string
    {
        return 'index';
    }

    public static function requiredRole(): string
    {
        return BlueprintRole::Table;
    }

    protected static function recordPage(): bool
    {
        return false;
    }
}
