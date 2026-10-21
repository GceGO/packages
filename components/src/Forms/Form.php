<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Concerns\HasColumns;
use Gcego\Components\Concerns\HasStatePath;
use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Form extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('forms.form')
            ->withConcerns(HasColumns::class, HasStatePath::class)
            ->withProperties(
                new PropertyDefinition('action', Type::string()),
                new PropertyDefinition('method', Type::string(), allowedValues: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
                new PropertyDefinition('disabled', Type::bool()),
            );
    }
}
