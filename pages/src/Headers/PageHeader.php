<?php

declare(strict_types=1);

namespace Gcego\Pages\Headers;

use Gcego\Navigation\Breadcrumbs\BreadcrumbCollection;

/**
 * The prepared header of an open page: plain-text title and description
 * (escaped when rendered), icon, breadcrumbs and the component paths of the
 * header actions and links (rendered by their own renderers).
 */
final readonly class PageHeader
{
    /**
     * @param  list<string>  $actions  Paths of the page's header actions and links.
     */
    public function __construct(
        public PageTitle $title,
        public ?PageDescription $description = null,
        public ?string $icon = null,
        public BreadcrumbCollection $breadcrumbs = new BreadcrumbCollection,
        public array $actions = [],
    ) {}
}
