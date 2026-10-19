<?php

declare(strict_types=1);

namespace Gcego\Pages\Contracts;

use Gcego\Components\Contracts\ComponentInterface;

/**
 * A component made for pages (Content, PageLink). Pages accept these as
 * children next to blueprint parts (tables, forms, infolists, actions,
 * layouts) and widgets.
 */
interface PageComponent extends ComponentInterface {}
