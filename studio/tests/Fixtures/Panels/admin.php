<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\Ability;

return new Panel(
    new Property(Panel::Name, 'admin'),
    new Property(Panel::Title, 'GCEGO Administration'),
    new Property(Panel::Path, 'admin'),
    new Property(Panel::Dashboard, 'overview'),
    new Property(Panel::Tenant, 'optional'),
    new Ability('access-admin'),
    new ModuleReference('academics'),
    new PageReference('reports'),
    new Branding(new Property(Branding::Name, 'GCEGO Admin')),
);
