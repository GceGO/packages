<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Requirements\Authenticated;

return new DashboardPage(
    new Property(DashboardPage::Name, 'home'),
    new Property(DashboardPage::Title, 'Home'),
    new Authenticated,

    new Section('Welcome', new Content('Your desk.')),
);
