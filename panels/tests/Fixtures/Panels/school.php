<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\TenantMember;

// One school's workspace: /school/{institution}/..., members of that institution only.
return new Panel(
    new Property(Panel::Name, 'school'),
    new Property(Panel::Title, 'School Portal'),
    new Property(Panel::Tenant, 'required'),
    new Property(Panel::Layout, 'topbar'),
    new Property(Panel::Order, 2),
    new TenantMember,

    new ModuleReference('academics'),
);
