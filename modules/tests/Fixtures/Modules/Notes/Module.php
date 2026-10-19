<?php

declare(strict_types=1);

use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Note;

// The minimal module: one resource, named after its model. It declares no
// Table, Form or Infolist, so it has no pages (diagnostics say so).
return new Module(
    new Resource(Note::class),
);
