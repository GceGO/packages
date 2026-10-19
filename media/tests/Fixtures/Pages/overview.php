<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Requirements\Authenticated;

return new DashboardPage(
    new Property(DashboardPage::Name, 'overview'),
    new Property(DashboardPage::Title, 'Overview'),
    new Authenticated,

    new Section('Summary', new Content('Everything at a glance.')),
);
