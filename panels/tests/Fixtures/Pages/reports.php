<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\CustomPage;
use Gcego\Security\Requirements\Ability;

// Only users the "view-reports" gate allows (Admin) may open it.
return new CustomPage(
    new Property(CustomPage::Name, 'reports'),
    new Property(CustomPage::Title, 'Reports'),
    new Property(CustomPage::NavigationGroup, 'Insights'),
    new Ability('view-reports'),

    new Content('Monthly figures.'),
);
