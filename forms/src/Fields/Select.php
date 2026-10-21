<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A native select:
 *
 *     new Select('category', 'Category', new Options(['science' => 'Science', 'arts' => 'Arts']))
 *     new Select('category_id', 'Category', new Relationship('category'))
 *     new Select('tags', 'Tags', new Property(Select::Multiple, true), new Relationship('tags'))
 *
 * Searchable adds a client-side filter box over the rendered options; it
 * never loads other options. Submitted values are validated against the
 * same server-side options.
 */
class Select extends ChoiceField
{
    public const string Multiple = 'multiple';

    public const string Searchable = 'searchable';

    public function view(): string
    {
        return 'gcego-forms::fields.select';
    }

    public function isMultiple(): bool
    {
        return $this->get(self::Multiple) === true;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Multiple, Type::bool(), default: false),
            new PropertyDefinition(self::Searchable, Type::bool(), default: false),
        );
    }
}
