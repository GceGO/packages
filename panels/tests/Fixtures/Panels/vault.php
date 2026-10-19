<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Panel;

// No requirement and no access policy: nobody may enter (deny by default).
return new Panel(
    new Property(Panel::Name, 'vault'),
    new Property(Panel::Hidden, true),
);
