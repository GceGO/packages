<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Panel;
use Gcego\Security\Permissions\Permission;

return new Panel(
    new Property(Panel::Name, 'desk'),
    new Property(Panel::Title, 'Desk'),
    new Property(Panel::Path, 'desk'),
    new Permission('panels.desk.access'),

    new ModuleReference('library'),
);
