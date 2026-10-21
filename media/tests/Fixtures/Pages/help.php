<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\CustomPage;
use Gcego\Security\Requirements\Authenticated;

// Not included in any panel: it has no route while panels route pages.
return new CustomPage(
    new Property(CustomPage::Name, 'help'),
    new Property(CustomPage::Title, 'Help'),
    new Authenticated,

    new Content('How to use the portal.'),
);
