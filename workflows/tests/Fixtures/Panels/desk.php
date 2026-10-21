<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\Ability;

return new Panel(
    new Property(Panel::Name, 'desk'),
    new Property(Panel::Title, 'Desk'),
    new Property(Panel::Path, 'desk'),
    new Property(Panel::Tenant, 'optional'),
    new Ability('use-desk'),

    new PageReference('home'),
    new ModuleReference('admissions'),
);
