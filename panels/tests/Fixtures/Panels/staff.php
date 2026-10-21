<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Panel;
use Gcego\Panels\Tests\Fixtures\Access\StaffAccess;

// A panel without a dashboard, admitted by an access policy.
return new Panel(
    new Property(Panel::Name, 'staff'),
    new Property(Panel::Title, 'Staff Room'),
    new Property(Panel::Layout, 'compact'),
    new Property(Panel::AccessPolicy, StaffAccess::class),
    new Property(Panel::Order, 3),

    // Admin-only (its Ability): entering the panel never bypasses page authorization.
    new PageReference('reports'),
);
