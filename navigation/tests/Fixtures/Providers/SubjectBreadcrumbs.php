<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Providers;

use Gcego\Navigation\Breadcrumbs\Breadcrumb;
use Gcego\Navigation\Contracts\BreadcrumbProvider;
use Gcego\Navigation\Resolution\NavigationContext;
use Gcego\Navigation\Tests\Fixtures\Models\Subject;

/**
 * "Chemistry" on a subject page, and "Edit" below it on its edit page.
 */
final class SubjectBreadcrumbs implements BreadcrumbProvider
{
    public function breadcrumbs(NavigationContext $context): iterable
    {
        $label = $context->record(Subject::class, $context->parameter('subject'));

        if ($label === null) {
            return [];
        }

        $crumbs = [new Breadcrumb($label, '/admin/subjects/'.$context->parameter('subject'))];

        if ($context->route === 'admin.subjects.edit') {
            $crumbs[] = new Breadcrumb('Edit');
        }

        return $crumbs;
    }
}
