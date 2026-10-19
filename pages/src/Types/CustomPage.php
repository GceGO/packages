<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Pages\Page;

/**
 * A page of any compatible GCEGO components (layouts, widgets, tables,
 * forms, infolists, actions, Content, PageLink), with the same routing,
 * authorization, header, breadcrumbs and Livewire hosting as every page.
 * Set Record to true for a page about one record.
 *
 *     new CustomPage(
 *         new Property(CustomPage::Name, 'reports.overview'),
 *         new Property(CustomPage::Title, 'Reports'),
 *         new Permission('reports.view'),
 *         new Tabs(new Tab('Attendance', …), new Tab('Fees', …)),
 *     )
 */
class CustomPage extends Page {}
