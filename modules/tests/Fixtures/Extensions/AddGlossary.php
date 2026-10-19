<?php

declare(strict_types=1);

namespace Gcego\Modules\Tests\Fixtures\Extensions;

use Gcego\Components\Property;
use Gcego\Modules\Contracts\ModuleExtension;
use Gcego\Modules\Module;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Security\Requirements\Authenticated;

/**
 * Adds a page to the academics module.
 */
final class AddGlossary implements ModuleExtension
{
    public function extends(string $module): bool
    {
        return $module === 'academics';
    }

    public function extend(Module $module): Module
    {
        return $module->withAppendedChildren(new Page(new Property(Page::Name, 'glossary'), new Property(Page::Title, 'Glossary'), new Authenticated, new Content('Terms.')));
    }
}
