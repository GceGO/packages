<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\TenantMember;

return new Panel(
    new Property(Panel::Name, 'school'),
    new Property(Panel::Title, 'School Portal'),
    new Property(Panel::Path, 'school'),
    new Property(Panel::Tenant, 'required'),
    new Property(Panel::Layout, 'topbar'),
    new TenantMember,
    new ModuleReference('academics'),
    new Branding(new Property(Branding::Name, 'School Portal')),
);
