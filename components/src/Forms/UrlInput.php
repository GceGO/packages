<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Schema\ComponentDefinition;

class UrlInput extends TextField
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('forms.urlInput');
    }
}
