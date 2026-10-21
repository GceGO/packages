<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\Ability;

return new Panel(
    new Property(Panel::Name, 'admin'),
    new Property(Panel::Title, 'Administration'),
    new Property(Panel::Path, 'admin'),
    new Property(Panel::Tenant, 'optional'),
    new Ability('access-admin'),
    new ModuleReference('academics'),
);
