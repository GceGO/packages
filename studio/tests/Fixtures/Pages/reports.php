<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Security\Requirements\Ability;

return new Page(
    new Property(Page::Name, 'reports'),
    new Property(Page::Title, 'Reports'),
    new Ability('view-reports'),

    new Content('Reports for administrators.'),
);
