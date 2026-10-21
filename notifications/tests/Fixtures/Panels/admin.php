<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Menu\UserMenuItem;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\Ability;

return new Panel(
    new Property(Panel::Name, 'admin'),
    new Property(Panel::Title, 'GCEGO Administration'),
    new Property(Panel::Description, 'Platform administration'),
    new Property(Panel::Path, 'admin'),
    new Property(Panel::Dashboard, 'overview'),
    new Property(Panel::Order, 1),
    new Property(Panel::Tenant, 'optional'),          // the institution selected in the session
    new Ability('access-admin'),

    new ModuleReference('academics'),
    new PageReference('reports'),

    new Branding(
        new Property(Branding::Name, 'GCEGO <Admin>'),
        new Property(Branding::Logo, '/images/logo.svg'),
        new Property(Branding::LogoDark, '/images/logo-dark.svg'),
        new Property(Branding::Favicon, '/favicon.ico'),
    ),
    new UserMenuItem('Profile', 'profile'),
);
